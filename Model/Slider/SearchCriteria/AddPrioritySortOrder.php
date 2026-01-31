<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider\SearchCriteria;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Slider\SearchCriteria\AddPrioritySortOrderInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrderBuilder;

/**
 * Add priority sort order to slider search criteria
 */
class AddPrioritySortOrder implements AddPrioritySortOrderInterface
{
    /**
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(SearchCriteriaInterface $searchCriteria): SearchCriteriaInterface
    {
        $sortOrder = $this->sortOrderBuilder
            ->setField(SliderInterface::PRIORITY)
            ->setAscendingDirection()
            ->create();

        $sortOrders = $searchCriteria->getSortOrders() ?? [];
        $sortOrders[] = $sortOrder;
        $searchCriteria->setSortOrders($sortOrders);

        return $searchCriteria;
    }
}
