<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerSearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Banner repository
 */
class BannerRepository implements BannerRepositoryInterface
{
    /**
     * @var array<int, BannerInterface>
     */
    private array $instances = [];

    /**
     * @param BannerResource $bannerResource
     * @param BannerFactory $bannerFactory
     * @param CollectionFactory $collectionFactory
     * @param BannerSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly BannerResource $bannerResource,
        private readonly BannerFactory $bannerFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly BannerSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(BannerInterface $banner): BannerInterface
    {
        try {
            $this->bannerResource->save($banner);
            unset($this->instances[$banner->getBannerId()]);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save banner: %1', $exception->getMessage()),
                $exception
            );
        }

        return $banner;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $bannerId): BannerInterface
    {
        if (isset($this->instances[$bannerId])) {
            return $this->instances[$bannerId];
        }

        $banner = $this->bannerFactory->create();
        $this->bannerResource->load($banner, $bannerId);

        if (!$banner->getBannerId()) {
            throw new NoSuchEntityException(__('Banner with id "%1" does not exist.', $bannerId));
        }

        $this->instances[$bannerId] = $banner;

        return $banner;
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
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(BannerInterface $banner): bool
    {
        try {
            $bannerId = $banner->getBannerId();
            $this->bannerResource->delete($banner);
            unset($this->instances[$bannerId]);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete banner: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $bannerId): bool
    {
        return $this->delete($this->getById($bannerId));
    }
}
