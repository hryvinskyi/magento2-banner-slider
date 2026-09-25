<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

use Hryvinskyi\BannerSliderApi\Api\Value\ResponsiveItem;

/**
 * Turns slider breakpoints that applied up to a maximum viewport width into the ascending min-width list that lays
 * the slides out the same way at every width.
 *
 * Max-width semantics, as the slider library applies them: every breakpoint whose width is at or above the viewport
 * width matches, and for each setting the smallest matching breakpoint that defines it wins; a setting no matching
 * breakpoint defines keeps its base value (one slide per page, the default gap). A breakpoint at width 0 matches no
 * real viewport, so it never applied.
 *
 * Min-width semantics, as the list is applied: every item at or below the viewport width matches and settings build
 * up from the smallest to the largest, so an item without a gap keeps the gap of the item before it. Where the
 * layout goes back to the default gap after a wider one, the item therefore states `0px`, the default gap.
 *
 * Each width range between two breakpoints becomes one item, starting one pixel above the lower breakpoint; ranges
 * that lay the slides out like the one before them are merged into it.
 */
class MaxWidthBreakpointTranslator
{
    /**
     * Slides per page where no breakpoint says otherwise
     */
    public const BASE_PER_PAGE = 1;

    /**
     * The gap that equals the default gap, stated where a wider gap must be undone
     */
    public const DEFAULT_GAP = '0px';

    /**
     * Translate max-width breakpoints into min-width items
     *
     * @param array<int,array{perPage:int|null,gap:string|null}> $settings Settings by maximum viewport width; a
     *     null value means the breakpoint does not set it. Gaps must be valid responsive item gaps
     * @return list<ResponsiveItem> Ascending by min width; empty when no breakpoint applies to a real viewport
     */
    public function translate(array $settings): array
    {
        $widths = array_values(array_filter(array_keys($settings), fn (int $width): bool => $width > 0));
        sort($widths);
        if ($widths === []) {
            return [];
        }

        $items = [];
        $previous = null;
        $effectiveGap = null;
        foreach ($this->rangeStarts($widths) as $index => $start) {
            $matching = array_slice($widths, $index);
            $perPage = $this->firstPerPage($settings, $matching) ?? self::BASE_PER_PAGE;
            $gap = $this->firstGap($settings, $matching);
            if ($previous === [$perPage, $gap]) {
                continue;
            }
            $items[] = new ResponsiveItem(
                $start,
                $perPage,
                $gap ?? ($effectiveGap === null ? null : self::DEFAULT_GAP)
            );
            $previous = [$perPage, $gap];
            $effectiveGap = $gap;
        }

        return $items;
    }

    /**
     * The first viewport width of each range: 0, then one pixel above each breakpoint width
     *
     * @param non-empty-list<int> $widths Ascending breakpoint widths
     * @return non-empty-list<int>
     */
    private function rangeStarts(array $widths): array
    {
        $starts = [0];
        foreach ($widths as $width) {
            $starts[] = $width + 1;
        }

        return $starts;
    }

    /**
     * Slides per page of the smallest of the breakpoints that sets them
     *
     * @param array<int,array{perPage:int|null,gap:string|null}> $settings
     * @param list<int> $widths Matching breakpoint widths, ascending
     * @return int|null
     */
    private function firstPerPage(array $settings, array $widths): ?int
    {
        foreach ($widths as $width) {
            $perPage = $settings[$width]['perPage'] ?? null;
            if ($perPage !== null) {
                return $perPage;
            }
        }

        return null;
    }

    /**
     * Gap of the smallest of the breakpoints that sets one
     *
     * @param array<int,array{perPage:int|null,gap:string|null}> $settings
     * @param list<int> $widths Matching breakpoint widths, ascending
     * @return string|null
     */
    private function firstGap(array $settings, array $widths): ?string
    {
        foreach ($widths as $width) {
            $gap = $settings[$width]['gap'] ?? null;
            if ($gap !== null) {
                return $gap;
            }
        }

        return null;
    }
}
