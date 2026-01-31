<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\Slider;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSlider\Model\Slider;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Slider collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = SliderInterface::SLIDER_ID;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'slider_collection';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Slider::class, SliderResource::class);
    }

    /**
     * Add store filter to collection
     *
     * @param int $storeId
     * @return $this
     */
    public function addStoreFilter(int $storeId): self
    {
        $this->getSelect()
            ->join(
                ['store_table' => $this->getTable(SliderResource::STORE_TABLE_NAME)],
                'main_table.slider_id = store_table.slider_id',
                []
            )
            ->where('store_table.store_id IN (?)', [0, $storeId])
            ->group('main_table.slider_id');

        return $this;
    }

    /**
     * Add customer group filter to collection
     *
     * @param int $customerGroupId
     * @return $this
     */
    public function addCustomerGroupFilter(int $customerGroupId): self
    {
        $this->getSelect()
            ->join(
                ['customer_group_table' => $this->getTable(SliderResource::CUSTOMER_GROUP_TABLE_NAME)],
                'main_table.slider_id = customer_group_table.slider_id',
                []
            )
            ->where('customer_group_table.customer_group_id = ?', $customerGroupId)
            ->group('main_table.slider_id');

        return $this;
    }

    /**
     * Add active filter to collection
     *
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter(SliderInterface::STATUS, 1);

        return $this;
    }

    /**
     * Add date filter to collection
     *
     * @param string|null $date
     * @return $this
     */
    public function addDateFilter(?string $date = null): self
    {
        $date = $date ?? (new \DateTime())->format('Y-m-d H:i:s');

        $this->getSelect()
            ->where('(from_date IS NULL OR from_date <= ?)', $date)
            ->where('(to_date IS NULL OR to_date >= ?)', $date);

        return $this;
    }
}
