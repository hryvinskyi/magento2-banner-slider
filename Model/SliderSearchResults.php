<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Slider search results
 */
class SliderSearchResults extends SearchResults implements SliderSearchResultsInterface
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
    public function setItems(array $items): SliderSearchResultsInterface
    {
        return parent::setItems($items);
    }
}
