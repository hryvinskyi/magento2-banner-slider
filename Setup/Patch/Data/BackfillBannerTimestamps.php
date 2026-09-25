<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Setup\Patch\Data;

use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Fills banner creation and update times that were never written.
 *
 * The time comes from the database (`CURRENT_TIMESTAMP` into a `timestamp` column stores the current instant
 * whatever the session time zone), so it matches the column defaults of banners saved from now on.
 */
class BackfillBannerTimestamps implements DataPatchInterface
{
    private const BANNER_TABLE = 'hryvinskyi_banner_slider_banner';
    private const COLUMNS = ['created_at', 'updated_at'];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $bannerTable = $this->moduleDataSetup->getTable(self::BANNER_TABLE);

        foreach (self::COLUMNS as $column) {
            $filled = $connection->update(
                $bannerTable,
                [$column => new Expression('CURRENT_TIMESTAMP')],
                [$column . ' IS NULL']
            );
            $this->logger->info(sprintf(
                'Banner slider migration: %d banners got their %s value.',
                $filled,
                $column
            ));
        }

        return $this;
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
