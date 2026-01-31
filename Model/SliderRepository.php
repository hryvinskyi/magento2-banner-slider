<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\SliderRepositoryInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Slider repository
 */
class SliderRepository implements SliderRepositoryInterface
{
    /**
     * @var array<int, SliderInterface>
     */
    private array $instances = [];

    /**
     * @param SliderResource $sliderResource
     * @param SliderFactory $sliderFactory
     * @param CollectionFactory $collectionFactory
     * @param SliderSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly SliderResource $sliderResource,
        private readonly SliderFactory $sliderFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SliderSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(SliderInterface $slider): SliderInterface
    {
        try {
            $this->sliderResource->save($slider);
            unset($this->instances[$slider->getSliderId()]);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save slider: %1', $exception->getMessage()),
                $exception
            );
        }

        return $slider;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $sliderId): SliderInterface
    {
        if (isset($this->instances[$sliderId])) {
            return $this->instances[$sliderId];
        }

        $slider = $this->sliderFactory->create();
        $this->sliderResource->load($slider, $sliderId);

        if (!$slider->getSliderId()) {
            throw new NoSuchEntityException(__('Slider with id "%1" does not exist.', $sliderId));
        }

        $this->instances[$sliderId] = $slider;

        return $slider;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SliderSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(SliderInterface $slider): bool
    {
        try {
            $sliderId = $slider->getSliderId();
            $this->sliderResource->delete($slider);
            unset($this->instances[$sliderId]);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete slider: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $sliderId): bool
    {
        return $this->delete($this->getById($sliderId));
    }
}
