<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Repository;

use Hryvinskyi\BannerSlider\Model\Breakpoint;
use Hryvinskyi\BannerSlider\Model\BreakpointFactory;
use Hryvinskyi\BannerSlider\Model\Media\CommittedMediaRemover;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint as BreakpointResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputFiles;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Validation\BreakpointValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Single-breakpoint persistence: save, load, search, delete, and the breakpoints of a slider in rendering order.
 *
 * Saving validates first and accepts only this package's breakpoint model, the one implementation of the data interface
 * that can be persisted. Nothing is cached between calls: every read goes to the database. Deleting a breakpoint
 * deletes its crops with it (a cascading key), and their output files are removed once the deletion is committed.
 */
class BreakpointRepository implements BreakpointRepositoryInterface
{
    /**
     * @param BreakpointResource $resource
     * @param BreakpointFactory $breakpointFactory
     * @param CollectionFactory $collectionFactory
     * @param BreakpointSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param BreakpointValidatorInterface $validator
     * @param EntityPersister $persister
     * @param CropOutputFiles $cropOutputFiles
     * @param CommittedMediaRemover $committedMediaRemover
     */
    public function __construct(
        private readonly BreakpointResource $resource,
        private readonly BreakpointFactory $breakpointFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly BreakpointSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly BreakpointValidatorInterface $validator,
        private readonly EntityPersister $persister,
        private readonly CropOutputFiles $cropOutputFiles,
        private readonly CommittedMediaRemover $committedMediaRemover
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(BreakpointInterface $breakpoint): BreakpointInterface
    {
        $model = $this->asModel($breakpoint);
        $this->validator->validate($model);
        $this->persister->save($this->resource, $model, __('The breakpoint could not be saved.'));

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $breakpointId): BreakpointInterface
    {
        $model = $this->breakpointFactory->create();
        $this->resource->load($model, $breakpointId);
        if ($model->getBreakpointId() === null) {
            throw new NoSuchEntityException(__('The breakpoint with id "%1" does not exist.', $breakpointId));
        }

        return $model;
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
        $searchResults->setItems($this->toList($collection->getItems()));
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(BreakpointInterface $breakpoint): void
    {
        $model = $this->asModel($breakpoint);
        $breakpointId = $model->getBreakpointId();
        $outputFiles = $breakpointId === null ? [] : $this->cropOutputFiles->ofBreakpoints([$breakpointId]);
        $this->persister->delete($this->resource, $model, __('The breakpoint could not be deleted.'));
        $this->committedMediaRemover->removeAfterCommit($outputFiles);
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $breakpointId): void
    {
        $this->delete($this->getById($breakpointId));
    }

    /**
     * @inheritDoc
     */
    public function getBySliderId(int $sliderId, bool $enabledOnly = false): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addSliderFilter($sliderId);
        if ($enabledOnly) {
            $collection->addEnabledFilter();
        }
        $collection->orderForRendering();

        return $this->toList($collection->getItems());
    }

    /**
     * The breakpoint as the persistable model
     *
     * @param BreakpointInterface $breakpoint
     * @return Breakpoint
     * @throws \InvalidArgumentException When it is another implementation of the data interface
     */
    private function asModel(BreakpointInterface $breakpoint): Breakpoint
    {
        if (!$breakpoint instanceof Breakpoint) {
            throw new \InvalidArgumentException(sprintf(
                'The breakpoint repository stores only %s objects, got %s.',
                Breakpoint::class,
                $breakpoint::class
            ));
        }

        return $breakpoint;
    }

    /**
     * Keep the data objects among the items of a loaded collection
     *
     * @param array<mixed> $items
     * @return list<BreakpointInterface>
     */
    private function toList(array $items): array
    {
        $list = [];
        foreach ($items as $item) {
            if ($item instanceof BreakpointInterface) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
