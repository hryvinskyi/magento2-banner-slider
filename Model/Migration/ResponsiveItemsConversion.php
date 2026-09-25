<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

/**
 * Outcome of converting one stored `responsive_items` value.
 *
 * An unchanged result means the value is already in list shape and must be left as stored; a changed result
 * carries the list-shaped JSON to store and a note for every adjustment made on the way. A reinterpreted result lays
 * the slides out differently from what reading the legacy widths as minimum widths would give.
 */
class ResponsiveItemsConversion
{
    /**
     * @param bool $changed Whether the stored value must be replaced
     * @param string $json The value to store (the original value when unchanged)
     * @param list<string> $notes Human-readable description of every adjustment
     * @param bool $reinterpreted Whether the result differs from reading the legacy widths as minimum widths
     */
    public function __construct(
        private readonly bool $changed,
        private readonly string $json,
        private readonly array $notes,
        private readonly bool $reinterpreted = false
    ) {
    }

    /**
     * Whether the stored value must be replaced
     *
     * @return bool
     */
    public function isChanged(): bool
    {
        return $this->changed;
    }

    /**
     * The value to store
     *
     * @return string
     */
    public function getJson(): string
    {
        return $this->json;
    }

    /**
     * Description of every adjustment made while converting
     *
     * @return list<string>
     */
    public function getNotes(): array
    {
        return $this->notes;
    }

    /**
     * Whether the result differs from reading the legacy widths as minimum widths
     *
     * @return bool
     */
    public function isReinterpreted(): bool
    {
        return $this->reinterpreted;
    }
}
