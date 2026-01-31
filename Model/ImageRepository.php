<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Image as ImageResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Image\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Data\ImageInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ImageSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ImageSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\ImageRepositoryInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Image repository
 */
class ImageRepository implements ImageRepositoryInterface
{
    /**
     * @var array<int, ImageInterface>
     */
    private array $instances = [];

    /**
     * @param ImageResource $imageResource
     * @param ImageFactory $imageFactory
     * @param CollectionFactory $collectionFactory
     * @param ImageSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly ImageResource $imageResource,
        private readonly ImageFactory $imageFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ImageSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(ImageInterface $image): ImageInterface
    {
        try {
            $this->imageResource->save($image);
            unset($this->instances[$image->getImageId()]);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save image: %1', $exception->getMessage()),
                $exception
            );
        }

        return $image;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $imageId): ImageInterface
    {
        if (isset($this->instances[$imageId])) {
            return $this->instances[$imageId];
        }

        $image = $this->imageFactory->create();
        $this->imageResource->load($image, $imageId);

        if (!$image->getImageId()) {
            throw new NoSuchEntityException(__('Image with id "%1" does not exist.', $imageId));
        }

        $this->instances[$imageId] = $image;

        return $image;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ImageSearchResultsInterface
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
    public function delete(ImageInterface $image): bool
    {
        try {
            $imageId = $image->getImageId();
            $this->imageResource->delete($image);
            unset($this->instances[$imageId]);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete image: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $imageId): bool
    {
        return $this->delete($this->getById($imageId));
    }
}
