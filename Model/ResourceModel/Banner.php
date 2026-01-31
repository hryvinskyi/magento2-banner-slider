<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * Banner resource model
 */
class Banner extends AbstractDb
{
    public const TABLE_NAME = 'hryvinskyi_banner_slider_banner';

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
        $this->_init(self::TABLE_NAME, BannerInterface::BANNER_ID);
    }

    /**
     * @inheritDoc
     */
    public function load(AbstractModel $object, $value, $field = null): self
    {
        $bannerId = $this->getBannerId($object, (int)$value, $field);

        if ($bannerId) {
            $this->entityManager->load($object, $bannerId);
        }

        return $this;
    }

    /**
     * Get banner ID by value and field
     *
     * @param AbstractModel $object
     * @param int $value
     * @param string|null $field
     * @return int|false
     */
    private function getBannerId(AbstractModel $object, int $value, ?string $field = null): int|false
    {
        $entityMetadata = $this->metadataPool->getMetadata(BannerInterface::class);
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
}
