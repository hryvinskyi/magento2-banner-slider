<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\SearchResults;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropSearchResultsInterface;

/**
 * A page of crops found by the crop repository, holding crop objects only.
 */
class ResponsiveCropSearchResults extends AbstractSearchResults implements ResponsiveCropSearchResultsInterface
{
    /**
     * @var list<ResponsiveCropInterface>
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
    public function setItems(array $items): ResponsiveCropSearchResultsInterface
    {
        $this->items = $items;

        return $this;
    }
}
