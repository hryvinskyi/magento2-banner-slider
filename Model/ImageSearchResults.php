<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\ImageSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Image search results
 */
class ImageSearchResults extends SearchResults implements ImageSearchResultsInterface
{
    /**
     * @inheritDoc
     */
    public function setItems(array $items): ImageSearchResultsInterface
    {
        return parent::setItems($items);
    }
}
