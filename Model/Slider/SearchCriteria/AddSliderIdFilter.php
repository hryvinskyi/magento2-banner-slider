<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider\SearchCriteria;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Slider\SearchCriteria\AddSliderIdFilterInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Add slider ID filter to search criteria
 */
class AddSliderIdFilter implements AddSliderIdFilterInterface
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
    public function apply(SearchCriteriaInterface $searchCriteria, int $sliderId): SearchCriteriaInterface
    {
        $filter = $this->filterBuilder
            ->setField(SliderInterface::SLIDER_ID)
            ->setValue($sliderId)
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
