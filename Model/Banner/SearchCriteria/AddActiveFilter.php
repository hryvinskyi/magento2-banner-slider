<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Banner\SearchCriteria;

use Hryvinskyi\BannerSliderApi\Api\Banner\SearchCriteria\AddActiveFilterInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Add active status filter to banner search criteria
 */
class AddActiveFilter implements AddActiveFilterInterface
{
    /**
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     */
    public function __construct(
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(SearchCriteriaInterface $searchCriteria): SearchCriteriaInterface
    {
        $filter = $this->filterBuilder
            ->setField(BannerInterface::STATUS)
            ->setValue(1)
            ->setConditionType('eq')
            ->create();

        $filterGroup = $this->filterGroupBuilder
            ->addFilter($filter)
            ->create();

        $filterGroups = $searchCriteria->getFilterGroups();
        $filterGroups[] = $filterGroup;
        $searchCriteria->setFilterGroups($filterGroups);

        return $searchCriteria;
    }
}
