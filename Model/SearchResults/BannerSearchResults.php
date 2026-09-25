<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\SearchResults;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerSearchResultsInterface;

/**
 * A page of banners found by the banner repository, holding banner objects only.
 */
class BannerSearchResults extends AbstractSearchResults implements BannerSearchResultsInterface
{
    /**
     * @var list<BannerInterface>
     */
    private array $items = [];

    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @inheritDoc
     */
    public function setItems(array $items): BannerSearchResultsInterface
    {
        $this->items = $items;

        return $this;
    }
}
