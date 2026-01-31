<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * Slider resource model
 */
class Slider extends AbstractDb
{
    public const TABLE_NAME = 'hryvinskyi_banner_slider';

    /**
     * @param Context $context
     * @param EntityManager $entityManager
     * @param MetadataPool $metadataPool
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly EntityManager $entityManager,
        private readonly MetadataPool $metadataPool,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, SliderInterface::SLIDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function load(AbstractModel $object, $value, $field = null): self
    {
        $sliderId = $this->getSliderId($object, (int)$value, $field);

        if ($sliderId) {
            $this->entityManager->load($object, $sliderId);
        }

        return $this;
    }

    /**
     * Get slider ID by value and field
     *
     * @param AbstractModel $object
     * @param int $value
     * @param string|null $field
     * @return int|false
     * @throws LocalizedException
     */
    private function getSliderId(AbstractModel $object, int $value, ?string $field = null): int|false
    {
        $entityMetadata = $this->metadataPool->getMetadata(SliderInterface::class);
        $field = $field ?: $entityMetadata->getIdentifierField();

        $entityId = $value;
        if ($field !== $entityMetadata->getIdentifierField()) {
            $select = $this->_getLoadSelect($field, $value, $object);
            $select->reset(Select::COLUMNS)
                ->columns($this->getMainTable() . '.' . $entityMetadata->getIdentifierField())
                ->limit(1);
            $result = $this->getConnection()->fetchCol($select);
            $entityId = count($result) ? (int)$result[0] : false;
        }

        return $entityId;
    }

    /**
     * @inheritDoc
     */
    public function save(AbstractModel $object): self
    {
        $this->entityManager->save($object);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function delete(AbstractModel $object): self
    {
        $this->entityManager->delete($object);

        return $this;
    }

    /**
     * Save store IDs for slider
     *
     * @param int $sliderId
     * @param int[] $storeIds
     * @return void
     */
    public function saveStoreIds(int $sliderId, array $storeIds): void
    {
        $connection = $this->getConnection();
        $tableName = $this->getTable(self::STORE_TABLE_NAME);

        $connection->delete($tableName, ['slider_id = ?' => $sliderId]);

        if (!empty($storeIds)) {
            $data = [];
            foreach ($storeIds as $storeId) {
                $data[] = [
                    'slider_id' => $sliderId,
                    'store_id' => (int)$storeId,
                ];
            }
            $connection->insertMultiple($tableName, $data);
        }
    }

    /**
     * Get customer group IDs for slider
     *
     * @param int $sliderId
     * @return int[]
     */
    public function getCustomerGroupIds(int $sliderId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::CUSTOMER_GROUP_TABLE_NAME), 'customer_group_id')
            ->where('slider_id = ?', $sliderId);

        $result = $connection->fetchCol($select);

        if (empty($result)) {
            $result = [GroupInterface::CUST_GROUP_ALL];
        } else {
            $result = array_map('intval', $result);
        }

        return $result;
    }

    /**
     * Save customer group IDs for slider
     *
     * @param int $sliderId
     * @param int[] $customerGroupIds
     * @return void
     */
    public function saveCustomerGroupIds(int $sliderId, array $customerGroupIds): void
    {
        $connection = $this->getConnection();
        $tableName = $this->getTable(self::CUSTOMER_GROUP_TABLE_NAME);

        $connection->delete($tableName, ['slider_id = ?' => $sliderId]);

        if (!empty($customerGroupIds)) {
            $data = [];
            foreach ($customerGroupIds as $customerGroupId) {
                $data[] = [
                    'slider_id' => $sliderId,
                    'customer_group_id' => (int)$customerGroupId,
                ];
            }
            $connection->insertMultiple($tableName, $data);
        }
    }
}
