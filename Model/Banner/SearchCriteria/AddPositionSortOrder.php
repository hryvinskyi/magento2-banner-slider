<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Banner\SearchCriteria;

use Hryvinskyi\BannerSliderApi\Api\Banner\SearchCriteria\AddPositionSortOrderInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrderBuilder;

/**
 * Add position sort order to banner search criteria
 */
class AddPositionSortOrder implements AddPositionSortOrderInterface
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
            ->setField(BannerInterface::POSITION)
            ->setAscendingDirection()
            ->create();

        $sortOrders = $searchCriteria->getSortOrders() ?? [];
        $sortOrders[] = $sortOrder;
        $searchCriteria->setSortOrders($sortOrders);

        return $searchCriteria;
    }
}
