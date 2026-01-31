<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider\Locator;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Slider\Locator\SliderLocatorInterface;

/**
 * Slider locator service
 */
class SliderLocator implements SliderLocatorInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getByLocation(string $location, int $storeId, int $customerGroupId): ?SliderInterface
    {
        $collection = $this->createFilteredCollection($storeId, $customerGroupId);
        $collection->addFieldToFilter(SliderInterface::LOCATION, $location);
        $collection->setOrder(SliderInterface::PRIORITY, 'ASC');

        return $this->getFirstSlider($collection);
    }

    /**
     * @inheritDoc
     */
    public function getById(int $sliderId, int $storeId, int $customerGroupId): ?SliderInterface
    {
        $collection = $this->createFilteredCollection($storeId, $customerGroupId);
        $collection->addFieldToFilter(SliderInterface::SLIDER_ID, $sliderId);

        return $this->getFirstSlider($collection);
    }

    /**
     * Create collection with common filters applied
     *
     * @param int $storeId
     * @param int $customerGroupId
     * @return Collection
     */
    private function createFilteredCollection(int $storeId, int $customerGroupId): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter();
        $collection->addDateFilter();
        $collection->addStoreFilter($storeId);
        $collection->addCustomerGroupFilter($customerGroupId);
        $collection->setPageSize(1);

        return $collection;
    }

    /**
     * Get first slider from collection or null if empty
     *
     * @param Collection $collection
     * @return SliderInterface|null
     */
    private function getFirstSlider(Collection $collection): ?SliderInterface
    {
        $slider = $collection->getFirstItem();

        return $slider->getSliderId() ? $slider : null;
    }
}
