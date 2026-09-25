<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Repository;

use Hryvinskyi\BannerSlider\Model\ResponsiveCrop;
use Hryvinskyi\BannerSlider\Model\ResponsiveCropFactory;
use Hryvinskyi\BannerSlider\Model\Media\CommittedMediaRemover;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputFiles;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Validation\ResponsiveCropValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Single-crop persistence: save, load, search, delete, and the crops of a banner.
 *
 * Saving validates first and accepts only this package's crop model, the one implementation of the data interface
 * that can be persisted. Nothing is cached between calls: every read goes to the database. A deleted crop's output
 * files are removed once the deletion is committed.
 */
class ResponsiveCropRepository implements ResponsiveCropRepositoryInterface
{
    /**
     * @param ResponsiveCropResource $resource
     * @param ResponsiveCropFactory $cropFactory
     * @param CollectionFactory $collectionFactory
     * @param ResponsiveCropSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param ResponsiveCropValidatorInterface $validator
     * @param EntityPersister $persister
     * @param CropOutputFiles $cropOutputFiles
     * @param CommittedMediaRemover $committedMediaRemover
     */
    public function __construct(
        private readonly ResponsiveCropResource $resource,
        private readonly ResponsiveCropFactory $cropFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ResponsiveCropSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly ResponsiveCropValidatorInterface $validator,
        private readonly EntityPersister $persister,
        private readonly CropOutputFiles $cropOutputFiles,
        private readonly CommittedMediaRemover $committedMediaRemover
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(ResponsiveCropInterface $crop): ResponsiveCropInterface
    {
        $model = $this->asModel($crop);
        $this->validator->validate($model);
        $this->persister->save($this->resource, $model, __('The crop could not be saved.'));

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $cropId): ResponsiveCropInterface
    {
        $model = $this->cropFactory->create();
        $this->resource->load($model, $cropId);
        if ($model->getCropId() === null) {
            throw new NoSuchEntityException(__('The crop with id "%1" does not exist.', $cropId));
        }

        return $model;
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
        $searchResults->setItems($this->toList($collection->getItems()));
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(ResponsiveCropInterface $crop): void
    {
        $model = $this->asModel($crop);
        $outputFiles = $this->cropOutputFiles->ofCrop($model);
        $this->persister->delete($this->resource, $model, __('The crop could not be deleted.'));
        $this->committedMediaRemover->removeAfterCommit($outputFiles);
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $cropId): void
    {
        $this->delete($this->getById($cropId));
    }

    /**
     * @inheritDoc
     */
    public function getByBannerId(int $bannerId, bool $enabledOnly = false): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addBannerFilter($bannerId);
        if ($enabledOnly) {
            $collection->addEnabledFilter();
        }

        return $this->toList($collection->getItems());
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

        return $this->toList($collection->getItems())[0] ?? null;
    }

    /**
     * The crop as the persistable model
     *
     * @param ResponsiveCropInterface $crop
     * @return ResponsiveCrop
     * @throws \InvalidArgumentException When it is another implementation of the data interface
     */
    private function asModel(ResponsiveCropInterface $crop): ResponsiveCrop
    {
        if (!$crop instanceof ResponsiveCrop) {
            throw new \InvalidArgumentException(sprintf(
                'The crop repository stores only %s objects, got %s.',
                ResponsiveCrop::class,
                $crop::class
            ));
        }

        return $crop;
    }

    /**
     * Keep the data objects among the items of a loaded collection
     *
     * @param array<mixed> $items
     * @return list<ResponsiveCropInterface>
     */
    private function toList(array $items): array
    {
        $list = [];
        foreach ($items as $item) {
            if ($item instanceof ResponsiveCropInterface) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
