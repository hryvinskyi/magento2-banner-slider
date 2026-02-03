<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Breakpoint search results
 */
class BreakpointSearchResults extends SearchResults implements BreakpointSearchResultsInterface
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
    public function setItems(array $items): BreakpointSearchResultsInterface
    {
        return parent::setItems($items);
    }
}
