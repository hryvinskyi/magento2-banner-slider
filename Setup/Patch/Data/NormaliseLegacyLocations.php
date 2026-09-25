<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyLocationNormaliser;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Rewrites every stored slider location that is not a valid location code into one (see LegacyLocationNormaliser).
 *
 * The storefront finds a slider only by a valid code, so without this a slider placed at an earlier free-text
 * location would silently disappear. Every rewrite is logged as `old → new` with the slider id: layouts and widgets
 * that name the old location must be changed to the new one. Two sliders may end up at the same location; their
 * priority then decides which one shows, as for any shared location. A location that leaves nothing is removed.
 */
class NormaliseLegacyLocations implements DataPatchInterface
{
    private const SLIDER_TABLE = 'hryvinskyi_banner_slider';
    private const COLUMN = 'location';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LegacyLocationNormaliser $locationNormaliser
     * @param TypedRowFetcher $rowFetcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LegacyLocationNormaliser $locationNormaliser,
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
        $locations = $this->rowFetcher->fetchIdValuePairs(
            $connection,
            $connection->select()
                ->from($sliderTable, ['slider_id', self::COLUMN])
                ->where(self::COLUMN . ' IS NOT NULL')
                ->where(self::COLUMN . " <> ''")
        );

        $rewritten = 0;
        foreach ($locations as $sliderId => $location) {
            $location = (string)$location;
            $code = $this->locationNormaliser->normalise($location);
            if ($code === $location) {
                continue;
            }
            $connection->update($sliderTable, [self::COLUMN => $code], ['slider_id = ?' => $sliderId]);
            $rewritten++;
            $this->logger->warning(
                sprintf(
                    'Banner slider migration: slider %d location "%s" → "%s"; change layouts and widgets that '
                    . 'place a slider at the old location.',
                    $sliderId,
                    $location,
                    $code ?? ''
                ),
                ['slider_id' => $sliderId, 'old_location' => $location, 'new_location' => $code]
            );
        }

        $this->logger->info(sprintf(
            'Banner slider migration: %d slider locations rewritten as valid location codes.',
            $rewritten
        ));

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [NormaliseLegacyRows::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
