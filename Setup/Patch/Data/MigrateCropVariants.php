<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\NonTransactionableInterface;
use Psr\Log\LoggerInterface;

/**
 * Moves the per-format crop columns (WebP and AVIF flag, quality and path) into crop variant rows, then drops them.
 *
 * A crop gets a variant for a format when it asked for that format or already has a generated file for it. The
 * quality is clamped to 1..100 and the path is kept as stored.
 *
 * Dropping a column is DDL, which the adapter refuses inside a transaction, so this patch manages its own: the copy
 * commits first and the columns are dropped afterwards. A run interrupted between the two repeats the copy
 * harmlessly (variants are upserted by crop and format). Without the legacy columns the patch does nothing.
 */
class MigrateCropVariants implements DataPatchInterface, NonTransactionableInterface
{
    private const CROP_TABLE = 'hryvinskyi_banner_slider_responsive_crop';
    private const VARIANT_TABLE = 'hryvinskyi_banner_slider_crop_variant';
    private const BATCH_SIZE = 500;
    private const MIN_QUALITY = 1;
    private const MAX_QUALITY = 100;

    /**
     * Legacy columns per variant format, with the quality the legacy schema defaulted to
     */
    private const LEGACY_FORMATS = [
        'webp' => ['flag' => 'generate_webp', 'path' => 'webp_image', 'quality' => 'webp_quality', 'default' => 85],
        'avif' => ['flag' => 'generate_avif', 'path' => 'avif_image', 'quality' => 'avif_quality', 'default' => 80],
    ];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param TypedRowFetcher $rowFetcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
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
        $cropTable = $this->moduleDataSetup->getTable(self::CROP_TABLE);
        $legacyColumns = $this->findLegacyColumns($connection, $cropTable);
        if ($legacyColumns === []) {
            return $this;
        }

        $variants = $this->collectVariants(
            $this->rowFetcher->fetchRows(
                $connection,
                $connection->select()->from($cropTable, array_merge(['crop_id'], $legacyColumns))
            )
        );

        $connection->beginTransaction();
        try {
            foreach (array_chunk($variants, self::BATCH_SIZE) as $chunk) {
                $connection->insertOnDuplicate(
                    $this->moduleDataSetup->getTable(self::VARIANT_TABLE),
                    $chunk,
                    ['quality', 'path']
                );
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        foreach ($legacyColumns as $column) {
            $connection->dropColumn($cropTable, $column);
        }

        $this->logger->info(sprintf(
            'Banner slider migration: %d crop variant rows written; legacy crop format columns dropped.',
            count($variants)
        ));

        return $this;
    }

    /**
     * The legacy per-format columns that still exist
     *
     * @param AdapterInterface $connection
     * @param string $cropTable
     * @return list<string>
     */
    private function findLegacyColumns(AdapterInterface $connection, string $cropTable): array
    {
        $columns = [];
        foreach (self::LEGACY_FORMATS as $legacy) {
            foreach ([$legacy['flag'], $legacy['path'], $legacy['quality']] as $column) {
                if ($connection->tableColumnExists($cropTable, $column)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * Build variant rows from legacy crop rows
     *
     * @param list<array<string,string|null>> $crops
     * @return list<array{crop_id: int, format: string, quality: int, path: string|null}>
     */
    private function collectVariants(array $crops): array
    {
        $variants = [];
        foreach ($crops as $crop) {
            foreach (self::LEGACY_FORMATS as $format => $legacy) {
                $path = $crop[$legacy['path']] ?? null;
                $path = $path === null || trim($path) === '' ? null : $path;
                if ($path === null && ($crop[$legacy['flag']] ?? '0') !== '1') {
                    continue;
                }
                $variants[] = [
                    'crop_id' => (int)($crop['crop_id'] ?? 0),
                    'format' => $format,
                    'quality' => $this->quality($crop[$legacy['quality']] ?? null, $legacy['default']),
                    'path' => $path,
                ];
            }
        }

        return $variants;
    }

    /**
     * Stored quality clamped to 1..100, or the legacy default when none is stored
     *
     * @param string|null $stored
     * @param int $default
     * @return int
     */
    private function quality(?string $stored, int $default): int
    {
        if ($stored === null || !is_numeric($stored)) {
            return $default;
        }

        return max(self::MIN_QUALITY, min(self::MAX_QUALITY, (int)$stored));
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
