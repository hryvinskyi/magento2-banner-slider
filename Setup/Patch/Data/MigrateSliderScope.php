<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyScopeParser;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\NonTransactionableInterface;
use Psr\Log\LoggerInterface;

/**
 * Moves slider store views and customer groups from comma-separated columns into link tables, then drops the columns.
 *
 * - Stores: ids that still exist become link rows; a slider left without any keeps no rows and stays visible
 *   nowhere, as the legacy storefront filter never matched an empty value.
 * - Groups: the legacy "all groups" marker sets `all_customer_groups`; otherwise the existing ids become link rows.
 *   A slider left without any group keeps the flag off and no rows, so it stays hidden from everyone.
 *
 * Dropping a column is DDL, which the adapter refuses inside a transaction, so this patch manages its own: the copy
 * commits first and the columns are dropped afterwards. A run interrupted between the two repeats the copy
 * harmlessly (the inserts are upserts). Without the legacy columns the patch does nothing.
 */
class MigrateSliderScope implements DataPatchInterface, NonTransactionableInterface
{
    private const SLIDER_TABLE = 'hryvinskyi_banner_slider';
    private const STORE_LINK_TABLE = 'hryvinskyi_banner_slider_store';
    private const GROUP_LINK_TABLE = 'hryvinskyi_banner_slider_customer_group';
    private const LEGACY_STORE_IDS = 'store_ids';
    private const LEGACY_GROUP_IDS = 'customer_group_ids';
    private const ALL_CUSTOMER_GROUPS = 'all_customer_groups';
    private const BATCH_SIZE = 500;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LegacyScopeParser $scopeParser
     * @param TypedRowFetcher $rowFetcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LegacyScopeParser $scopeParser,
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
        $hasStores = $connection->tableColumnExists($sliderTable, self::LEGACY_STORE_IDS);
        $hasGroups = $connection->tableColumnExists($sliderTable, self::LEGACY_GROUP_IDS);
        if (!$hasStores && !$hasGroups) {
            return $this;
        }

        $connection->beginTransaction();
        try {
            if ($hasStores) {
                $this->migrateStores($connection, $sliderTable);
            }
            if ($hasGroups) {
                $this->migrateCustomerGroups($connection, $sliderTable);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        if ($hasStores) {
            $connection->dropColumn($sliderTable, self::LEGACY_STORE_IDS);
        }
        if ($hasGroups) {
            $connection->dropColumn($sliderTable, self::LEGACY_GROUP_IDS);
        }

        return $this;
    }

    /**
     * Copy legacy store ids into the store link table
     *
     * @param AdapterInterface $connection
     * @param string $sliderTable
     * @return void
     */
    private function migrateStores(AdapterInterface $connection, string $sliderTable): void
    {
        $legacy = $this->rowFetcher->fetchIdValuePairs(
            $connection,
            $connection->select()->from($sliderTable, ['slider_id', self::LEGACY_STORE_IDS])
        );
        $existing = $this->rowFetcher->fetchIds(
            $connection,
            $connection->select()->from($this->moduleDataSetup->getTable('store'), ['store_id'])
        );

        $rows = [];
        $storeless = [];
        foreach ($legacy as $sliderId => $value) {
            $storeIds = $this->scopeParser->parseStoreIds($value, $existing);
            if ($storeIds === []) {
                $storeless[] = $sliderId;
                continue;
            }
            foreach ($storeIds as $storeId) {
                $rows[] = ['slider_id' => $sliderId, 'store_id' => $storeId];
            }
        }
        $this->insert($connection, self::STORE_LINK_TABLE, $rows);

        $this->logger->info(sprintf(
            'Banner slider migration: %d store view link rows written for %d sliders.',
            count($rows),
            count($legacy) - count($storeless)
        ));
        if ($storeless !== []) {
            $this->logger->warning(
                'Banner slider migration: sliders without an existing store view stay visible nowhere.',
                ['slider_ids' => $storeless]
            );
        }
    }

    /**
     * Copy legacy customer group ids into the flag and the group link table
     *
     * @param AdapterInterface $connection
     * @param string $sliderTable
     * @return void
     */
    private function migrateCustomerGroups(AdapterInterface $connection, string $sliderTable): void
    {
        $legacy = $this->rowFetcher->fetchIdValuePairs(
            $connection,
            $connection->select()->from($sliderTable, ['slider_id', self::LEGACY_GROUP_IDS])
        );
        $existing = $this->rowFetcher->fetchIds(
            $connection,
            $connection->select()->from($this->moduleDataSetup->getTable('customer_group'), ['customer_group_id'])
        );

        $rows = [];
        $allGroups = [];
        $listedGroups = [];
        $hidden = [];
        foreach ($legacy as $sliderId => $value) {
            $scope = $this->scopeParser->parseCustomerGroups($value, $existing);
            if ($scope->isAllGroups()) {
                $allGroups[] = $sliderId;
                continue;
            }
            $listedGroups[] = $sliderId;
            if ($scope->isNone()) {
                $hidden[] = $sliderId;
            }
            foreach ($scope->getGroupIds() as $groupId) {
                $rows[] = ['slider_id' => $sliderId, 'customer_group_id' => $groupId];
            }
        }

        $this->setAllCustomerGroups($connection, $sliderTable, $allGroups, true);
        $this->setAllCustomerGroups($connection, $sliderTable, $listedGroups, false);
        $this->insert($connection, self::GROUP_LINK_TABLE, $rows);

        $this->logger->info(sprintf(
            'Banner slider migration: %d sliders for all customer groups, %d customer group link rows written.',
            count($allGroups),
            count($rows)
        ));
        if ($hidden !== []) {
            $this->logger->warning(
                'Banner slider migration: sliders without an existing customer group stay hidden from everyone.',
                ['slider_ids' => $hidden]
            );
        }
    }

    /**
     * Set the "all customer groups" flag on the given sliders
     *
     * @param AdapterInterface $connection
     * @param string $sliderTable
     * @param list<int> $sliderIds
     * @param bool $value
     * @return void
     */
    private function setAllCustomerGroups(
        AdapterInterface $connection,
        string $sliderTable,
        array $sliderIds,
        bool $value
    ): void {
        foreach (array_chunk($sliderIds, self::BATCH_SIZE) as $chunk) {
            $connection->update(
                $sliderTable,
                [self::ALL_CUSTOMER_GROUPS => (int)$value],
                ['slider_id IN (?)' => $chunk]
            );
        }
    }

    /**
     * Upsert link rows in batches
     *
     * @param AdapterInterface $connection
     * @param string $table Table name without prefix
     * @param list<array<string,int>> $rows
     * @return void
     */
    private function insert(AdapterInterface $connection, string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            $connection->insertOnDuplicate($this->moduleDataSetup->getTable($table), $chunk);
        }
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
