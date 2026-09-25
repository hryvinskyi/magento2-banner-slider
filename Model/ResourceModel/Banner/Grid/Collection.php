<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\Grid;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Rows of the admin banner listing, one per banner, filterable by slider.
 *
 * Its main table and resource model are set in `etc/di.xml`, so it can be injected wherever a framework database
 * collection is expected.
 */
class Collection extends SearchResult
{
    /**
     * Keep banners of the slider
     *
     * @param int $sliderId
     * @return $this
     */
    public function addSliderFilter(int $sliderId): self
    {
        $this->getSelect()->where('main_table.' . BannerInterface::SLIDER_ID . ' = ?', $sliderId);

        return $this;
    }
}
