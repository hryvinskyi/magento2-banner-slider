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
 * Tells whether two min-width lists lay the slides out the same way at every viewport width.
 *
 * At a width, every item at or below it applies, smallest first: the largest one sets the slides per page, and the
 * largest one with a gap sets the gap. Without a matching item a slider shows one slide per page with the default
 * gap; a zero gap is the default gap. Two lists can differ as data (a redundant item, `0px` against no gap) and still
 * lay slides out alike.
 */
class ResponsiveLayoutComparator
{
    private const ZERO_GAP_PATTERN = '/^0+(\.0+)?[a-z%]+$/';

    /**
     * Whether both lists give the same slides per page and gap at every viewport width
     *
     * @param list<ResponsiveItem> $first
     * @param list<ResponsiveItem> $second
     * @return bool
     */
    public function isSameLayout(array $first, array $second): bool
    {
        $widths = [0];
        foreach ([...$first, ...$second] as $item) {
            $widths[] = $item->getMinWidth();
        }
        foreach (array_unique($widths) as $width) {
            if ($this->layoutAt($first, $width) !== $this->layoutAt($second, $width)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Slides per page and gap (null for the default) at a viewport width
     *
     * @param list<ResponsiveItem> $items
     * @param int $width
     * @return array{0: int, 1: string|null}
     */
    private function layoutAt(array $items, int $width): array
    {
        usort($items, fn (ResponsiveItem $a, ResponsiveItem $b): int => $a->getMinWidth() <=> $b->getMinWidth());
        $perPage = MaxWidthBreakpointTranslator::BASE_PER_PAGE;
        $gap = null;
        foreach ($items as $item) {
            if ($item->getMinWidth() > $width) {
                break;
            }
            $perPage = $item->getPerPage();
            $itemGap = $item->getGap();
            if ($itemGap !== null) {
                $gap = preg_match(self::ZERO_GAP_PATTERN, $itemGap) === 1 ? null : $itemGap;
            }
        }

        return [$perPage, $gap];
    }
}
