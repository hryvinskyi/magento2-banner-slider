<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Responsive crop search results
 */
class ResponsiveCropSearchResults extends SearchResults implements ResponsiveCropSearchResultsInterface
{
    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @inheritDoc
     */
    public function setItems(array $items): ResponsiveCropSearchResultsInterface
    {
        return parent::setItems($items);
    }
}
