<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Api\SearchCriteria\CollectionProcessor\FilterProcessor;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\Collection as SliderCollection;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;

/**
 * Search criteria filter `customer_group_id` on sliders: with `eq`/`in` it keeps sliders shown to any of the given
 * customer groups, including sliders shown to all customer groups; with `neq`/`nin` it keeps exactly the other
 * sliders.
 */
class SliderCustomerGroupFilter implements CustomFilterInterface
{
    /**
     * @param FilterIdList $filterIdList
     */
    public function __construct(
        private readonly FilterIdList $filterIdList
    ) {
    }

    /**
     * Apply the customer group filter to a slider collection
     *
     * @param Filter $filter
     * @param AbstractDb $collection
     * @return bool
     * @throws \InvalidArgumentException When the collection is not a slider collection, or the condition is not
     *     eq, in, neq or nin
     */
    public function apply(Filter $filter, AbstractDb $collection): bool
    {
        if (!$collection instanceof SliderCollection) {
            throw new \InvalidArgumentException(sprintf(
                'The customer group filter applies to %s only, got %s.',
                SliderCollection::class,
                $collection::class
            ));
        }
        $ids = $this->filterIdList->read($filter);
        if ($this->filterIdList->excludes($filter)) {
            $collection->excludeCustomerGroups($ids);

            return true;
        }
        $collection->addCustomerGroupFilter($ids);

        return true;
    }
}
