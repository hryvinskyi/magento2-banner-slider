<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Banner\SearchCriteria;

use Hryvinskyi\BannerSliderApi\Api\Banner\SearchCriteria\AddDateFromDateToFilterInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Add date range filter to banner search criteria
 */
class AddDateFromDateToFilter implements AddDateFromDateToFilterInterface
{
    /**
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(SearchCriteriaInterface $searchCriteria): SearchCriteriaInterface
    {
        $currentDate = $this->dateTime->gmtDate('Y-m-d H:i:s');
        $filterGroups = $searchCriteria->getFilterGroups();

        $fromDateNullFilter = $this->filterBuilder
            ->setField(BannerInterface::FROM_DATE)
            ->setValue(true)
            ->setConditionType('null')
            ->create();

        $fromDateFilter = $this->filterBuilder
            ->setField(BannerInterface::FROM_DATE)
            ->setValue($currentDate)
            ->setConditionType('lteq')
            ->create();

        $filterGroups[] = $this->filterGroupBuilder
            ->addFilter($fromDateNullFilter)
            ->addFilter($fromDateFilter)
            ->create();

        $toDateNullFilter = $this->filterBuilder
            ->setField(BannerInterface::TO_DATE)
            ->setValue(true)
            ->setConditionType('null')
            ->create();

        $toDateFilter = $this->filterBuilder
            ->setField(BannerInterface::TO_DATE)
            ->setValue($currentDate)
            ->setConditionType('gteq')
            ->create();

        $filterGroups[] = $this->filterGroupBuilder
            ->addFilter($toDateNullFilter)
            ->addFilter($toDateFilter)
            ->create();

        $searchCriteria->setFilterGroups($filterGroups);

        return $searchCriteria;
    }
}
