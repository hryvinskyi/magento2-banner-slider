<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\SearchResults;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderSearchResultsInterface;

/**
 * A page of sliders found by the slider repository, holding slider objects only.
 */
class SliderSearchResults extends AbstractSearchResults implements SliderSearchResultsInterface
{
    /**
     * @var list<SliderInterface>
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
    public function setItems(array $items): SliderSearchResultsInterface
    {
        $this->items = $items;

        return $this;
    }
}
