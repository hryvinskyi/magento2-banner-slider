<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Banner;

use DateTimeInterface;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Banner\VisibleBannersProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;

/**
 * The slides of a slider at a moment, in one query: enabled banners of the slider whose active window contains the
 * moment, by position and then id.
 */
class VisibleBannersProvider implements VisibleBannersProviderInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getForSlider(int $sliderId, DateTimeInterface $at): array
    {
        if ($sliderId < 1) {
            return [];
        }

        $collection = $this->collectionFactory->create()
            ->addSliderFilter($sliderId)
            ->addEnabledFilter()
            ->addActiveAtFilter($at)
            ->orderByPosition();

        $banners = [];
        foreach ($collection->getItems() as $item) {
            if ($item instanceof BannerInterface) {
                $banners[] = $item;
            }
        }

        return $banners;
    }
}
