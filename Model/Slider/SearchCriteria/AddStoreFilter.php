<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider\SearchCriteria;

use Hryvinskyi\BannerSliderApi\Api\Slider\SearchCriteria\AddStoreFilterInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Add store filter to slider search criteria
 */
class AddStoreFilter implements AddStoreFilterInterface
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
    public function apply(SearchCriteriaInterface $searchCriteria, int $storeId): SearchCriteriaInterface
    {
        $filter = $this->filterBuilder
            ->setField('store_id')
            ->setValue([0, $storeId])
            ->setConditionType('in')
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
