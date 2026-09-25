<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\SearchResults;

use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointSearchResultsInterface;

/**
 * A page of breakpoints found by the breakpoint repository, holding breakpoint objects only.
 */
class BreakpointSearchResults extends AbstractSearchResults implements BreakpointSearchResultsInterface
{
    /**
     * @var list<BreakpointInterface>
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
    public function setItems(array $items): BreakpointSearchResultsInterface
    {
        $this->items = $items;

        return $this;
    }
}
