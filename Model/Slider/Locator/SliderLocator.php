<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider\Locator;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Slider\Locator\SliderLocatorInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Slider locator service
 */
class SliderLocator implements SliderLocatorInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param CustomerSession $customerSession
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getByLocation(string $location): ?SliderInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(SliderInterface::LOCATION, $location);
        $collection->addActiveFilter();
        $collection->addDateFilter();
        $collection->addStoreFilter((int)$this->storeManager->getStore()->getId());
        $collection->addCustomerGroupFilter((int)$this->customerSession->getCustomerGroupId());
        $collection->setOrder(SliderInterface::PRIORITY, 'ASC');
        $collection->setPageSize(1);

        $slider = $collection->getFirstItem();

        return $slider->getSliderId() ? $slider : null;
    }
}
