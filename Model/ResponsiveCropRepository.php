<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Responsive crop repository
 */
class ResponsiveCropRepository implements ResponsiveCropRepositoryInterface
{
    /**
     * @var array<int, ResponsiveCropInterface>
     */
    private array $instances = [];

    /**
     * @param ResponsiveCropResource $responsiveCropResource
     * @param ResponsiveCropFactory $responsiveCropFactory
     * @param CollectionFactory $collectionFactory
     * @param ResponsiveCropSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly ResponsiveCropResource $responsiveCropResource,
        private readonly ResponsiveCropFactory $responsiveCropFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ResponsiveCropSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(ResponsiveCropInterface $responsiveCrop): ResponsiveCropInterface
    {
        try {
            $this->responsiveCropResource->save($responsiveCrop);
            unset($this->instances[$responsiveCrop->getCropId()]);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save responsive crop: %1', $exception->getMessage()),
                $exception
            );
        }

        return $responsiveCrop;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $cropId): ResponsiveCropInterface
    {
        if (isset($this->instances[$cropId])) {
            return $this->instances[$cropId];
        }

        $responsiveCrop = $this->responsiveCropFactory->create();
        $this->responsiveCropResource->load($responsiveCrop, $cropId);

        if (!$responsiveCrop->getCropId()) {
            throw new NoSuchEntityException(__('Responsive crop with id "%1" does not exist.', $cropId));
        }

        $this->instances[$cropId] = $responsiveCrop;

        return $responsiveCrop;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ResponsiveCropSearchResultsInterface
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
    public function getByBannerId(int $bannerId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addBannerFilter($bannerId);
        $collection->addActiveFilter();
        $collection->joinBreakpointData();
        $collection->addSortOrderOrdering();

        return $collection->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getByBannerIds(array $bannerIds): array
    {
        if (empty($bannerIds)) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addBannerIdsFilter($bannerIds);
        $collection->addActiveFilter();
        $collection->joinBreakpointData();
        $collection->addSortOrderOrdering();

        $result = [];
        foreach ($bannerIds as $bannerId) {
            $result[$bannerId] = [];
        }

        foreach ($collection->getItems() as $crop) {
            $bannerId = (int)$crop->getBannerId();
            $result[$bannerId][] = $crop;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getByBannerAndBreakpoint(int $bannerId, int $breakpointId): ?ResponsiveCropInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addBannerFilter($bannerId);
        $collection->addBreakpointFilter($breakpointId);
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();

        if (!$item->getCropId()) {
            return null;
        }

        return $item;
    }

    /**
     * @inheritDoc
     */
    public function delete(ResponsiveCropInterface $responsiveCrop): bool
    {
        try {
            $cropId = $responsiveCrop->getCropId();
            $this->responsiveCropResource->delete($responsiveCrop);
            unset($this->instances[$cropId]);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete responsive crop: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $cropId): bool
    {
        return $this->delete($this->getById($cropId));
    }
}
