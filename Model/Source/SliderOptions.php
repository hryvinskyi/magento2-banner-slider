<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Source;

use Hryvinskyi\BannerSliderApi\Api\SliderRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Every slider, valued by id and labelled by name, in name order; read through the slider repository.
 */
class SliderOptions implements OptionSourceInterface
{
    /**
     * @param SliderRepositoryInterface $sliderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        private readonly SliderRepositoryInterface $sliderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    /**
     * Slider options for select fields and grid filters
     *
     * @return list<array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        $sortOrder = $this->sortOrderBuilder
            ->setField(SliderInterface::NAME)
            ->setDirection(SortOrder::SORT_ASC)
            ->create();
        $criteria = $this->searchCriteriaBuilder->addSortOrder($sortOrder)->create();

        $options = [];
        foreach ($this->sliderRepository->getList($criteria)->getItems() as $slider) {
            $sliderId = $slider->getSliderId();
            if ($sliderId !== null) {
                $options[] = ['value' => $sliderId, 'label' => $slider->getName()];
            }
        }

        return $options;
    }
}
