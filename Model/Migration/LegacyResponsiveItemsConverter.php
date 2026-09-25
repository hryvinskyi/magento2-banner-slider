<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Data\ResponsiveItemsCodec;
use Hryvinskyi\BannerSliderApi\Api\Value\ResponsiveItem;

/**
 * Converts a stored `responsive_items` value from the legacy object shape to the list shape, keeping the layout the
 * legacy storefront showed.
 *
 * Legacy shape, keyed by viewport width: `{"0":{"items":1},"768":{"items":3}}`. The legacy storefront
 * handed those keys to the slider library without a media query mode, so they were **maximum** widths: the example
 * showed 3 slides up to 768px and the base of one slide above it. List shape, ascending **minimum** widths:
 * `[{"min_width":0,"per_page":3,"gap":null},{"min_width":769,"per_page":1,"gap":null}]`. The translation is
 * MaxWidthBreakpointTranslator's.
 *
 * - The shape is decided by the JSON type (object or array), never by the keys: `{"0":{…}}` decodes to a PHP array
 *   whose only key is 0, which would pass for a list. A value already in list shape is left as it is.
 * - Values are brought inside the `ResponsiveItem` rules: slides per page clamped to 1..10, a bare number as gap read
 *   as pixels. A value that cannot be read (slides per page that are not a number, a gap that is not a CSS length) is
 *   treated as not set. Keys without a list-shape equivalent (`nav`, `dots`, `autoplay`, …) are dropped. Every
 *   adjustment is reported as a note.
 * - When the result lays slides out differently from reading the keys as minimum widths, the conversion says so, with
 *   both lists in a note, so the change can be reviewed.
 */
class LegacyResponsiveItemsConverter
{
    private const LEGACY_PER_PAGE = 'items';
    private const LEGACY_GAP = 'gap';

    /**
     * @param ResponsiveItemsCodec $codec Writes the list shape the slider model reads
     * @param MaxWidthBreakpointTranslator $translator
     * @param ResponsiveLayoutComparator $layoutComparator
     */
    public function __construct(
        private readonly ResponsiveItemsCodec $codec,
        private readonly MaxWidthBreakpointTranslator $translator,
        private readonly ResponsiveLayoutComparator $layoutComparator
    ) {
    }

    /**
     * Convert one stored value
     *
     * @param string $stored The stored JSON
     * @return ResponsiveItemsConversion
     */
    public function convert(string $stored): ResponsiveItemsConversion
    {
        try {
            $decoded = json_decode(trim($stored), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new ResponsiveItemsConversion(true, '[]', ['The value is not valid JSON; stored an empty list.']);
        }

        if (is_array($decoded)) {
            return new ResponsiveItemsConversion(false, $stored, []);
        }
        if (!$decoded instanceof \stdClass) {
            return new ResponsiveItemsConversion(
                true,
                '[]',
                ['The value is neither a JSON object nor a JSON list; stored an empty list.']
            );
        }

        $notes = [];
        $settings = $this->readSettings($decoded, $notes);
        $items = $this->translator->translate($settings);
        $json = $this->codec->encode($items);
        $minWidthReading = $this->minWidthReading($settings);
        $reinterpreted = !$this->layoutComparator->isSameLayout($minWidthReading, $items);
        if ($reinterpreted) {
            $notes[] = sprintf(
                'The widths were maximum widths on the earlier storefront; read as minimum widths they would have '
                . 'given %s, the same layout is %s.',
                $this->codec->encode($minWidthReading),
                $json
            );
        }

        return new ResponsiveItemsConversion(true, $json, $notes, $reinterpreted);
    }

    /**
     * The settings of every usable entry, by viewport width
     *
     * @param \stdClass $decoded
     * @param list<string> $notes
     * @return array<int,array{perPage: int|null, gap: string|null}>
     */
    private function readSettings(\stdClass $decoded, array &$notes): array
    {
        $settings = [];
        foreach (get_object_vars($decoded) as $key => $entry) {
            $key = (string)$key;
            if (!ctype_digit($key)) {
                $notes[] = sprintf('Dropped the entry "%s": its key is not a viewport width.', $key);
                continue;
            }
            if (!$entry instanceof \stdClass) {
                $notes[] = sprintf('Dropped the entry for width %s: its settings are not an object.', $key);
                continue;
            }
            $settings[(int)$key] = $this->readEntry((int)$key, $entry, $notes);
        }

        return $settings;
    }

    /**
     * Read the slides per page and gap of one legacy entry, recording every adjustment
     *
     * @param int $width
     * @param \stdClass $entry
     * @param list<string> $notes
     * @return array{perPage: int|null, gap: string|null}
     */
    private function readEntry(int $width, \stdClass $entry, array &$notes): array
    {
        $perPage = null;
        $gap = null;
        foreach (get_object_vars($entry) as $name => $value) {
            $name = (string)$name;
            if ($name === self::LEGACY_PER_PAGE) {
                $perPage = $this->readPerPage($width, $value, $notes);
                continue;
            }
            if ($name === self::LEGACY_GAP) {
                $gap = $this->readGap($width, $value, $notes);
                continue;
            }
            $notes[] = sprintf('Dropped "%s" at width %d: the list shape has no such setting.', $name, $width);
        }

        return ['perPage' => $perPage, 'gap' => $gap];
    }

    /**
     * Read slides per page, clamped to the allowed range; null when it is not set or not a number
     *
     * @param int $width
     * @param mixed $value
     * @param list<string> $notes
     * @return int|null
     */
    private function readPerPage(int $width, mixed $value, array &$notes): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            $notes[] = sprintf('Slides per page at width %d is not a number; ignored.', $width);

            return null;
        }

        $perPage = (int)$value;
        $clamped = max(1, min(ResponsiveItem::MAX_PER_PAGE, $perPage));
        if ($clamped !== $perPage) {
            $notes[] = sprintf('Slides per page at width %d was %d; clamped to %d.', $width, $perPage, $clamped);
        }

        return $clamped;
    }

    /**
     * Read the gap as a CSS length; a bare non-negative number means pixels; null when it is not set or invalid
     *
     * @param int $width
     * @param mixed $value
     * @param list<string> $notes
     * @return string|null
     */
    private function readGap(int $width, mixed $value, array &$notes): ?string
    {
        if ($value === null) {
            return null;
        }
        $gap = match (true) {
            is_int($value), is_float($value) => (string)$value,
            is_string($value) => trim($value),
            default => null,
        };
        if ($gap !== null && preg_match('/^\d+(\.\d+)?$/', $gap) === 1) {
            $notes[] = sprintf('Gap %s at width %d read as %spx.', $gap, $width, $gap);
            $gap .= 'px';
        }
        if ($gap !== null && $this->isCssLength($gap)) {
            return $gap;
        }
        $notes[] = sprintf('Dropped the gap at width %d: it is not a CSS length.', $width);

        return null;
    }

    /**
     * Whether a gap is a CSS length a responsive item accepts
     *
     * @param string $gap
     * @return bool
     */
    private function isCssLength(string $gap): bool
    {
        try {
            new ResponsiveItem(0, 1, $gap);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * The list the entries would give if their widths were read as minimum widths
     *
     * @param array<int,array{perPage:int|null,gap:string|null}> $settings
     * @return list<ResponsiveItem>
     */
    private function minWidthReading(array $settings): array
    {
        ksort($settings);
        $items = [];
        foreach ($settings as $width => $entry) {
            $items[] = new ResponsiveItem(
                $width,
                $entry['perPage'] ?? MaxWidthBreakpointTranslator::BASE_PER_PAGE,
                $entry['gap']
            );
        }

        return $items;
    }
}
