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
use Magento\Customer\Api\Data\GroupInterface;
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
            ->where('FIND_IN_SET(0, store_ids) OR FIND_IN_SET(?, store_ids)', $storeId);

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
            ->where(
                'FIND_IN_SET(' . GroupInterface::CUST_GROUP_ALL . ', customer_group_ids) OR FIND_IN_SET(?, customer_group_ids)',
                $customerGroupId
            );

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
