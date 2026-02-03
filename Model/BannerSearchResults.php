<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Banner search results
 */
class BannerSearchResults extends SearchResults implements BannerSearchResultsInterface
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
    public function setItems(array $items): BannerSearchResultsInterface
    {
        return parent::setItems($items);
    }
}
