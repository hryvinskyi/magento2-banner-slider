<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyResponsiveItemsConverter;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\NonTransactionableInterface;
use Psr\Log\LoggerInterface;

/**
 * Rewrites every stored slider `responsive_items` value from the legacy object shape to the list shape, keeping the
 * layout the legacy storefront showed (see LegacyResponsiveItemsConverter).
 *
 * - The legacy storefront applied the responsive items only while the slider's `is_responsive` flag was on. A slider
 *   with the flag off gets no responsive items, and the flag column is then dropped: the column is left out of the
 *   schema whitelist, so the schema engine keeps it until this patch has read it.
 * - Values already in list shape are left untouched; every adjustment is logged per slider, and a slider whose
 *   converted list lays slides out differently from reading its widths as minimum widths is logged as a warning.
 *
 * Dropping a column is not allowed inside a transaction, so the patch is not transactional: the row changes run in
 * a transaction of its own, and the column is dropped after its commit. A re-run after a crash in between is
 * harmless: converted values are in list shape and are left alone.
 */
class MigrateResponsiveItems implements DataPatchInterface, NonTransactionableInterface
{
    private const SLIDER_TABLE = 'hryvinskyi_banner_slider';
    private const COLUMN = 'responsive_items';
    private const LEGACY_FLAG = 'is_responsive';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LegacyResponsiveItemsConverter $converter
     * @param TypedRowFetcher $rowFetcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LegacyResponsiveItemsConverter $converter,
        private readonly TypedRowFetcher $rowFetcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $sliderTable = $this->moduleDataSetup->getTable(self::SLIDER_TABLE);
        $hasFlag = $connection->tableColumnExists($sliderTable, self::LEGACY_FLAG);

        $connection->beginTransaction();
        try {
            if ($hasFlag) {
                $this->clearItemsOfNonResponsiveSliders($connection, $sliderTable);
            }
            $this->convertItems($connection, $sliderTable);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        if ($hasFlag) {
            $connection->dropColumn($sliderTable, self::LEGACY_FLAG);
        }

        return $this;
    }

    /**
     * Remove the responsive items of sliders whose responsive flag is off: the legacy storefront ignored them
     *
     * @param AdapterInterface $connection
     * @param string $sliderTable
     * @return void
     */
    private function clearItemsOfNonResponsiveSliders(AdapterInterface $connection, string $sliderTable): void
    {
        $sliderIds = $this->rowFetcher->fetchIds(
            $connection,
            $connection->select()
                ->from($sliderTable, ['slider_id'])
                ->where(self::LEGACY_FLAG . ' = ?', 0)
                ->where(self::COLUMN . ' IS NOT NULL')
                ->where(self::COLUMN . " <> ''")
        );
        if ($sliderIds === []) {
            return;
        }

        $connection->update($sliderTable, [self::COLUMN => null], ['slider_id IN (?)' => $sliderIds]);
        $this->logger->info(
            'Banner slider migration: responsive items removed from sliders whose responsive flag was off; '
            . 'the earlier storefront ignored them.',
            ['slider_ids' => $sliderIds]
        );
    }

    /**
     * Convert the stored values that are still in the legacy shape
     *
     * @param AdapterInterface $connection
     * @param string $sliderTable
     * @return void
     */
    private function convertItems(AdapterInterface $connection, string $sliderTable): void
    {
        $stored = $this->rowFetcher->fetchIdValuePairs(
            $connection,
            $connection->select()
                ->from($sliderTable, ['slider_id', self::COLUMN])
                ->where(self::COLUMN . ' IS NOT NULL')
                ->where(self::COLUMN . " <> ''")
        );

        $converted = 0;
        foreach ($stored as $sliderId => $value) {
            $conversion = $this->converter->convert((string)$value);
            if (!$conversion->isChanged()) {
                continue;
            }
            $connection->update(
                $sliderTable,
                [self::COLUMN => $conversion->getJson()],
                ['slider_id = ?' => $sliderId]
            );
            $converted++;
            foreach ($conversion->getNotes() as $note) {
                $this->logger->info(
                    sprintf('Banner slider migration: slider %d responsive items: %s', $sliderId, $note)
                );
            }
            if ($conversion->isReinterpreted()) {
                $this->logger->warning(
                    sprintf(
                        'Banner slider migration: slider %d responsive items were maximum widths on the earlier '
                        . 'storefront; converted to the minimum widths that keep its layout. Review them.',
                        $sliderId
                    ),
                    ['stored' => $value, 'converted' => $conversion->getJson()]
                );
            }
        }

        $this->logger->info(sprintf(
            'Banner slider migration: responsive items of %d sliders converted to the list shape.',
            $converted
        ));
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
