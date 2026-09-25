<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\Slider;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Visibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;

/**
 * Reads and writes a slider's store view and customer group link rows.
 *
 * Reads work in bulk (one query per link table for any number of sliders), so collections attach the lists to
 * every loaded row without a query per row. Writes replace one slider's rows: rows no longer wanted are deleted,
 * new ones inserted, unchanged ones kept. With "all customer groups" on, the slider keeps no group rows.
 */
class VisibilityLinks
{
    public const STORE_TABLE = 'hryvinskyi_banner_slider_store';
    public const CUSTOMER_GROUP_TABLE = 'hryvinskyi_banner_slider_customer_group';
    public const STORE_ID = 'store_id';
    public const CUSTOMER_GROUP_ID = 'customer_group_id';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Store view ids per slider; every requested slider is a key
     *
     * @param list<int> $sliderIds
     * @return array<int, list<int>>
     */
    public function fetchStoreIds(array $sliderIds): array
    {
        return $this->fetch(self::STORE_TABLE, self::STORE_ID, $sliderIds);
    }

    /**
     * Customer group ids per slider; every requested slider is a key
     *
     * @param list<int> $sliderIds
     * @return array<int, list<int>>
     */
    public function fetchCustomerGroupIds(array $sliderIds): array
    {
        return $this->fetch(self::CUSTOMER_GROUP_TABLE, self::CUSTOMER_GROUP_ID, $sliderIds);
    }

    /**
     * Set the store and customer group id lists on rows that carry a `slider_id`, two queries in total
     *
     * @param array<DataObject> $rows
     * @return void
     */
    public function attach(array $rows): void
    {
        $sliderIds = [];
        foreach ($rows as $row) {
            $sliderId = $row->getData(SliderInterface::SLIDER_ID);
            if (is_numeric($sliderId) && (int)$sliderId > 0) {
                $sliderIds[] = (int)$sliderId;
            }
        }
        $sliderIds = array_values(array_unique($sliderIds));
        if ($sliderIds === []) {
            return;
        }

        $storeIds = $this->fetchStoreIds($sliderIds);
        $customerGroupIds = $this->fetchCustomerGroupIds($sliderIds);
        foreach ($rows as $row) {
            $sliderId = $row->getData(SliderInterface::SLIDER_ID);
            if (!is_numeric($sliderId)) {
                continue;
            }
            $row->setData(SliderInterface::STORE_IDS, $storeIds[(int)$sliderId] ?? []);
            $row->setData(SliderInterface::CUSTOMER_GROUP_IDS, $customerGroupIds[(int)$sliderId] ?? []);
        }
    }

    /**
     * Make the slider's link rows match its visibility
     *
     * @param int $sliderId
     * @param Visibility $visibility
     * @return void
     */
    public function replace(int $sliderId, Visibility $visibility): void
    {
        $this->replaceRows(self::STORE_TABLE, self::STORE_ID, $sliderId, $visibility->getStoreIds());
        $this->replaceRows(
            self::CUSTOMER_GROUP_TABLE,
            self::CUSTOMER_GROUP_ID,
            $sliderId,
            $visibility->isForAllCustomerGroups() ? [] : $visibility->getCustomerGroupIds()
        );
    }

    /**
     * Read one link table for many sliders
     *
     * @param string $table
     * @param string $column
     * @param list<int> $sliderIds
     * @return array<int, list<int>>
     */
    private function fetch(string $table, string $column, array $sliderIds): array
    {
        $result = array_fill_keys($sliderIds, []);
        if ($sliderIds === []) {
            return $result;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName($table), [SliderInterface::SLIDER_ID, $column])
            ->where(SliderInterface::SLIDER_ID . ' IN (?)', $sliderIds)
            ->order([SliderInterface::SLIDER_ID, $column]);

        foreach ($connection->fetchAll($select) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sliderId = $row[SliderInterface::SLIDER_ID] ?? null;
            $linkedId = $row[$column] ?? null;
            if (is_numeric($sliderId) && is_numeric($linkedId) && isset($result[(int)$sliderId])) {
                $result[(int)$sliderId][] = (int)$linkedId;
            }
        }

        return $result;
    }

    /**
     * Delete the rows no longer wanted and insert the missing ones
     *
     * @param string $table
     * @param string $column
     * @param int $sliderId
     * @param list<int> $wantedIds
     * @return void
     */
    private function replaceRows(string $table, string $column, int $sliderId, array $wantedIds): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName($table);

        $where = [SliderInterface::SLIDER_ID . ' = ?' => $sliderId];
        if ($wantedIds !== []) {
            $where[$column . ' NOT IN (?)'] = $wantedIds;
        }
        $connection->delete($tableName, $where);

        if ($wantedIds === []) {
            return;
        }
        $rows = [];
        foreach ($wantedIds as $linkedId) {
            $rows[] = [SliderInterface::SLIDER_ID => $sliderId, $column => $linkedId];
        }
        $connection->insertOnDuplicate($tableName, $rows, [$column]);
    }
}
