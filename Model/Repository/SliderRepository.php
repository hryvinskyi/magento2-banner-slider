<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Repository;

use Hryvinskyi\BannerSlider\Model\Media\BannerMediaCleaner;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\CollectionFactory as BannerCollectionFactory;
use Hryvinskyi\BannerSlider\Model\Slider;
use Hryvinskyi\BannerSlider\Model\SliderFactory;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\SliderRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Validation\SliderValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Single-slider persistence: save, load, search, delete.
 *
 * Saving validates first and accepts only this package's slider model, the one implementation of the data interface
 * that can be persisted. Nothing is cached between calls: every read goes to the database.
 *
 * Deleting a slider deletes its banners and their crops through the database cascade, which leaves their crop files
 * behind. The banner ids are therefore read before the delete, and once it succeeded each banner's crop folder is
 * removed, after the outermost commit when the delete runs inside a caller's transaction, so a rolled-back delete
 * keeps its files (a failure there is logged, never thrown: the rows are already gone).
 */
class SliderRepository implements SliderRepositoryInterface
{
    /**
     * @param SliderResource $resource
     * @param SliderFactory $sliderFactory
     * @param CollectionFactory $collectionFactory
     * @param SliderSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param SliderValidatorInterface $validator
     * @param EntityPersister $persister
     * @param BannerCollectionFactory $bannerCollectionFactory
     * @param BannerMediaCleaner $bannerMediaCleaner
     */
    public function __construct(
        private readonly SliderResource $resource,
        private readonly SliderFactory $sliderFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SliderSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SliderValidatorInterface $validator,
        private readonly EntityPersister $persister,
        private readonly BannerCollectionFactory $bannerCollectionFactory,
        private readonly BannerMediaCleaner $bannerMediaCleaner
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(SliderInterface $slider): SliderInterface
    {
        $model = $this->asModel($slider);
        $this->validator->validate($model);
        $this->persister->save($this->resource, $model, __('The slider could not be saved.'));

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $sliderId): SliderInterface
    {
        $model = $this->sliderFactory->create();
        $this->resource->load($model, $sliderId);
        if ($model->getSliderId() === null) {
            throw new NoSuchEntityException(__('The slider with id "%1" does not exist.', $sliderId));
        }

        return $model;
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
        $searchResults->setItems($this->toList($collection->getItems()));
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(SliderInterface $slider): void
    {
        $model = $this->asModel($slider);
        $sliderId = $model->getSliderId();
        $bannerIds = $sliderId === null ? [] : $this->bannerIdsOf($sliderId);
        $this->persister->delete($this->resource, $model, __('The slider could not be deleted.'));
        foreach ($bannerIds as $bannerId) {
            $this->bannerMediaCleaner->removeAfterCommit($bannerId);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $sliderId): void
    {
        $this->delete($this->getById($sliderId));
    }

    /**
     * Ids of the slider's banners
     *
     * @param int $sliderId
     * @return list<int>
     */
    private function bannerIdsOf(int $sliderId): array
    {
        $ids = [];
        foreach ($this->bannerCollectionFactory->create()->addSliderFilter($sliderId)->getAllIds() as $id) {
            if (is_numeric($id)) {
                $ids[] = (int)$id;
            }
        }

        return $ids;
    }

    /**
     * The slider as the persistable model
     *
     * @param SliderInterface $slider
     * @return Slider
     * @throws \InvalidArgumentException When it is another implementation of the data interface
     */
    private function asModel(SliderInterface $slider): Slider
    {
        if (!$slider instanceof Slider) {
            throw new \InvalidArgumentException(sprintf(
                'The slider repository stores only %s objects, got %s.',
                Slider::class,
                $slider::class
            ));
        }

        return $slider;
    }

    /**
     * Keep the data objects among the items of a loaded collection
     *
     * @param array<mixed> $items
     * @return list<SliderInterface>
     */
    private function toList(array $items): array
    {
        $list = [];
        foreach ($items as $item) {
            if ($item instanceof SliderInterface) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
