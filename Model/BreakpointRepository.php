<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint as BreakpointResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointSearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Breakpoint repository
 */
class BreakpointRepository implements BreakpointRepositoryInterface
{
    /**
     * @var array<int, BreakpointInterface>
     */
    private array $instances = [];

    /**
     * @param BreakpointResource $breakpointResource
     * @param BreakpointFactory $breakpointFactory
     * @param CollectionFactory $collectionFactory
     * @param BreakpointSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly BreakpointResource $breakpointResource,
        private readonly BreakpointFactory $breakpointFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly BreakpointSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(BreakpointInterface $breakpoint): BreakpointInterface
    {
        try {
            $this->breakpointResource->save($breakpoint);
            unset($this->instances[$breakpoint->getBreakpointId()]);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save breakpoint: %1', $exception->getMessage()),
                $exception
            );
        }

        return $breakpoint;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $breakpointId): BreakpointInterface
    {
        if (isset($this->instances[$breakpointId])) {
            return $this->instances[$breakpointId];
        }

        $breakpoint = $this->breakpointFactory->create();
        $this->breakpointResource->load($breakpoint, $breakpointId);

        if (!$breakpoint->getBreakpointId()) {
            throw new NoSuchEntityException(__('Breakpoint with id "%1" does not exist.', $breakpointId));
        }

        $this->instances[$breakpointId] = $breakpoint;

        return $breakpoint;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): BreakpointSearchResultsInterface
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
    public function getBySliderId(int $sliderId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addSliderFilter($sliderId);
        $collection->addActiveFilter();
        $collection->addSortOrderOrdering();

        return $collection->getItems();
    }

    /**
     * @inheritDoc
     */
    public function delete(BreakpointInterface $breakpoint): bool
    {
        try {
            $breakpointId = $breakpoint->getBreakpointId();
            $this->breakpointResource->delete($breakpoint);
            unset($this->instances[$breakpointId]);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete breakpoint: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $breakpointId): bool
    {
        return $this->delete($this->getById($breakpointId));
    }
}
