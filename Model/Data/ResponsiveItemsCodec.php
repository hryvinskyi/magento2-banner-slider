<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Data;

use Hryvinskyi\BannerSliderApi\Api\Value\ResponsiveItem;

/**
 * The stored form of a slider's slides-per-page rules: a JSON list, ascending by min width.
 *
 * Shape: `[{"min_width":0,"per_page":1,"gap":null},{"min_width":768,"per_page":3,"gap":"12px"}]`.
 * Decoding never throws: a value that is not a JSON list reads as no rules, and an entry that breaks a
 * `ResponsiveItem` rule is left out.
 */
class ResponsiveItemsCodec
{
    public const KEY_MIN_WIDTH = 'min_width';
    public const KEY_PER_PAGE = 'per_page';
    public const KEY_GAP = 'gap';

    /**
     * Encode rules in the stored shape, ascending by min width
     *
     * @param list<ResponsiveItem> $items
     * @return string
     */
    public function encode(array $items): string
    {
        $rows = [];
        foreach ($this->sort($items) as $item) {
            $rows[] = [
                self::KEY_MIN_WIDTH => $item->getMinWidth(),
                self::KEY_PER_PAGE => $item->getPerPage(),
                self::KEY_GAP => $item->getGap(),
            ];
        }

        return json_encode($rows, JSON_THROW_ON_ERROR);
    }

    /**
     * Decode a stored value; anything unreadable is left out
     *
     * @param string|null $stored
     * @return list<ResponsiveItem>
     */
    public function decode(?string $stored): array
    {
        if ($stored === null || trim($stored) === '') {
            return [];
        }

        try {
            $decoded = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $row) {
            $item = is_array($row) ? $this->toItem($row) : null;
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $this->sort($items);
    }

    /**
     * Build one rule from a decoded row, or null when the row breaks a rule
     *
     * @param array<mixed> $row
     * @return ResponsiveItem|null
     */
    private function toItem(array $row): ?ResponsiveItem
    {
        $minWidth = $row[self::KEY_MIN_WIDTH] ?? null;
        $perPage = $row[self::KEY_PER_PAGE] ?? null;
        $gap = $row[self::KEY_GAP] ?? null;
        if (!is_int($minWidth) || !is_int($perPage) || ($gap !== null && !is_string($gap))) {
            return null;
        }

        try {
            return new ResponsiveItem($minWidth, $perPage, $gap);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Order rules ascending by min width, keeping the given order among equal widths
     *
     * @param list<ResponsiveItem> $items
     * @return list<ResponsiveItem>
     */
    private function sort(array $items): array
    {
        usort(
            $items,
            fn (ResponsiveItem $left, ResponsiveItem $right): int => $left->getMinWidth() <=> $right->getMinWidth()
        );

        return $items;
    }
}
