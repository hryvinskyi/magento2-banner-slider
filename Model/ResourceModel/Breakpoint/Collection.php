<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint;

use Hryvinskyi\BannerSlider\Model\Breakpoint;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint as BreakpointResource;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Breakpoint collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = BreakpointInterface::BREAKPOINT_ID;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_breakpoint_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'breakpoint_collection';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Breakpoint::class, BreakpointResource::class);
    }

    /**
     * Add slider filter to collection
     *
     * @param int $sliderId
     * @return $this
     */
    public function addSliderFilter(int $sliderId): self
    {
        $this->addFieldToFilter(BreakpointInterface::SLIDER_ID, $sliderId);
        return $this;
    }

    /**
     * Add active filter to collection
     *
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter(BreakpointInterface::STATUS, 1);
        return $this;
    }

    /**
     * Add sort order ordering
     *
     * @param string $direction
     * @return $this
     */
    public function addSortOrderOrdering(string $direction = self::SORT_ORDER_ASC): self
    {
        $this->setOrder(BreakpointInterface::SORT_ORDER, $direction);
        return $this;
    }

    /**
     * Add min width ordering (descending for desktop-first)
     *
     * @param string $direction
     * @return $this
     */
    public function addMinWidthOrdering(string $direction = self::SORT_ORDER_DESC): self
    {
        $this->setOrder(BreakpointInterface::MIN_WIDTH, $direction);
        return $this;
    }
}
