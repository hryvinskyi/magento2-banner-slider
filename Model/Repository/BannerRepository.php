<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Repository;

use Hryvinskyi\BannerSlider\Model\Banner;
use Hryvinskyi\BannerSlider\Model\BannerFactory;
use Hryvinskyi\BannerSlider\Model\Media\BannerMediaCleaner;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Validation\BannerValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Single-banner persistence: save, load, search, delete.
 *
 * Saving validates first and accepts only this package's banner model, the one implementation of the data interface
 * that can be persisted. Nothing is cached between calls: every read goes to the database. Once a banner is deleted,
 * its crop output folder is removed too, after the outermost commit when the delete runs inside a caller's
 * transaction, so a rolled-back delete keeps its files.
 */
class BannerRepository implements BannerRepositoryInterface
{
    /**
     * @param BannerResource $resource
     * @param BannerFactory $bannerFactory
     * @param CollectionFactory $collectionFactory
     * @param BannerSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param BannerValidatorInterface $validator
     * @param EntityPersister $persister
     * @param BannerMediaCleaner $mediaCleaner
     */
    public function __construct(
        private readonly BannerResource $resource,
        private readonly BannerFactory $bannerFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly BannerSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly BannerValidatorInterface $validator,
        private readonly EntityPersister $persister,
        private readonly BannerMediaCleaner $mediaCleaner
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(BannerInterface $banner): BannerInterface
    {
        $model = $this->asModel($banner);
        $this->validator->validate($model);
        $this->persister->save($this->resource, $model, __('The banner could not be saved.'));

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $bannerId): BannerInterface
    {
        $model = $this->bannerFactory->create();
        $this->resource->load($model, $bannerId);
        if ($model->getBannerId() === null) {
            throw new NoSuchEntityException(__('The banner with id "%1" does not exist.', $bannerId));
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): BannerSearchResultsInterface
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
    public function delete(BannerInterface $banner): void
    {
        $model = $this->asModel($banner);
        $bannerId = $model->getBannerId();
        $this->persister->delete($this->resource, $model, __('The banner could not be deleted.'));
        if ($bannerId !== null) {
            $this->mediaCleaner->removeAfterCommit($bannerId);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $bannerId): void
    {
        $this->delete($this->getById($bannerId));
    }

    /**
     * The banner as the persistable model
     *
     * @param BannerInterface $banner
     * @return Banner
     * @throws \InvalidArgumentException When it is another implementation of the data interface
     */
    private function asModel(BannerInterface $banner): Banner
    {
        if (!$banner instanceof Banner) {
            throw new \InvalidArgumentException(sprintf(
                'The banner repository stores only %s objects, got %s.',
                Banner::class,
                $banner::class
            ));
        }

        return $banner;
    }

    /**
     * Keep the data objects among the items of a loaded collection
     *
     * @param array<mixed> $items
     * @return list<BannerInterface>
     */
    private function toList(array $items): array
    {
        $list = [];
        foreach ($items as $item) {
            if ($item instanceof BannerInterface) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
