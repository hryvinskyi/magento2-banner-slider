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
 * Breakpoints of sliders, in rendering order: widest first.
 */
class Collection extends AbstractCollection
{
    private const MAIN_TABLE_ALIAS = 'main_table';

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
     * Keep breakpoints of the slider
     *
     * @param int $sliderId
     * @return $this
     */
    public function addSliderFilter(int $sliderId): self
    {
        $this->getSelect()->where($this->mainColumn(BreakpointInterface::SLIDER_ID) . ' = ?', $sliderId);

        return $this;
    }

    /**
     * Keep breakpoints of any of the sliders; an empty list keeps none
     *
     * @param list<int> $sliderIds
     * @return $this
     */
    public function addSliderIdsFilter(array $sliderIds): self
    {
        if ($sliderIds === []) {
            $this->getSelect()->where('1 = 0');

            return $this;
        }
        $this->getSelect()->where($this->mainColumn(BreakpointInterface::SLIDER_ID) . ' IN (?)', $sliderIds);

        return $this;
    }

    /**
     * Keep enabled breakpoints
     *
     * @return $this
     */
    public function addEnabledFilter(): self
    {
        $this->getSelect()->where($this->mainColumn(BreakpointInterface::STATUS) . ' = ?', 1);

        return $this;
    }

    /**
     * Keep breakpoints with the identifier
     *
     * @param string $identifier
     * @return $this
     */
    public function addIdentifierFilter(string $identifier): self
    {
        $this->getSelect()->where($this->mainColumn(BreakpointInterface::IDENTIFIER) . ' = ?', $identifier);

        return $this;
    }

    /**
     * Order for rendering: min width descending, then sort order ascending, then id ascending
     *
     * @return $this
     */
    public function orderForRendering(): self
    {
        $this->getSelect()
            ->order($this->mainColumn(BreakpointInterface::MIN_WIDTH) . ' ' . self::SORT_ORDER_DESC)
            ->order($this->mainColumn(BreakpointInterface::SORT_ORDER) . ' ' . self::SORT_ORDER_ASC)
            ->order($this->mainColumn(BreakpointInterface::BREAKPOINT_ID) . ' ' . self::SORT_ORDER_ASC);

        return $this;
    }

    /**
     * Column of the main table, qualified with its alias
     *
     * @param string $column
     * @return string
     */
    private function mainColumn(string $column): string
    {
        return self::MAIN_TABLE_ALIAS . '.' . $column;
    }
}
