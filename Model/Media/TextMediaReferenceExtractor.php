<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyMediaPathNormaliser;

/**
 * Finds the media files a piece of stored HTML or CSS points at, as media-relative paths.
 *
 * - `{{media url="…"}}` directives, quoted or not, also when the editor stored the quotes as entities (`&quot;`);
 * - `src`, `srcset`, `href`, `poster`, `data-src` and `data-srcset` attribute values and CSS `url(…)` values whose
 *   path has a `media/` segment (`/media/x.jpg`, `/pub/media/x.jpg`, `https://host/media/x.jpg`): the part after that
 *   segment, without query string or fragment, URL-decoded.
 *
 * Values that cannot be a safe media-relative path are left out. The result errs on the side of listing a path:
 * it tells which files must be kept, so an extra entry costs nothing and a missing one would lose a file.
 */
class TextMediaReferenceExtractor
{
    private const DIRECTIVE_PATTERN = '/\{\{\s*media\s+url\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s}"\']+))/i';
    private const ATTRIBUTE_PATTERN
        = '/\b(?:data-)?(?:src|srcset|href|poster)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+))/i';
    private const CSS_URL_PATTERN = '/\burl\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s"\']*))\s*\)/i';
    private const URL_PREFIX_PATTERN = '#^(?:[a-z][a-z0-9+.-]*:)?//[^/]*#i';
    private const MEDIA_SEGMENT_PATTERN = '#(?:^|/)media/(.+)$#';

    /**
     * Entities an editor stores quotes and ampersands as, which would otherwise hide a quoted value
     */
    private const QUOTE_ENTITIES = [
        '&quot;' => '"',
        '&#34;' => '"',
        '&#x22;' => '"',
        '&apos;' => "'",
        '&#39;' => "'",
        '&#039;' => "'",
        '&#x27;' => "'",
        '&amp;' => '&',
    ];

    /**
     * @param LegacyMediaPathNormaliser $normaliser
     */
    public function __construct(
        private readonly LegacyMediaPathNormaliser $normaliser
    ) {
    }

    /**
     * Media-relative paths referenced by the text, without duplicates
     *
     * @param string $text HTML or CSS
     * @return list<string>
     */
    public function extract(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }
        $text = strtr($text, self::QUOTE_ENTITIES);

        $paths = [];
        foreach ($this->matches(self::DIRECTIVE_PATTERN, $text) as $value) {
            $paths[] = $this->normaliser->normalise($value);
        }
        foreach ($this->matches(self::ATTRIBUTE_PATTERN, $text) as $value) {
            foreach ($this->candidates($value) as $candidate) {
                $paths[] = $this->fromUrl($candidate);
            }
        }
        foreach ($this->matches(self::CSS_URL_PATTERN, $text) as $value) {
            $paths[] = $this->fromUrl($value);
        }

        return array_values(array_unique(array_filter($paths, fn (?string $path): bool => $path !== null)));
    }

    /**
     * The captured value of every match; the pattern captures the value in one of three alternative groups
     *
     * @param string $pattern
     * @param string $text
     * @return list<string>
     */
    private function matches(string $pattern, string $text): array
    {
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $values = [];
        foreach ($matches as $match) {
            $value = trim(($match[1] ?? '') . ($match[2] ?? '') . ($match[3] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * The URLs of an attribute value: one for `src`/`href`, each candidate's URL for a `srcset`
     *
     * @param string $value
     * @return list<string>
     */
    private function candidates(string $value): array
    {
        $urls = [];
        foreach (explode(',', $value) as $candidate) {
            $url = preg_split('/\s+/', trim($candidate))[0] ?? '';
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * The media-relative path a URL points at, or null when it does not point into media
     *
     * @param string $url
     * @return string|null
     */
    private function fromUrl(string $url): ?string
    {
        if (str_starts_with($url, '{{')) {
            return null;
        }
        $path = (string)preg_replace(self::URL_PREFIX_PATTERN, '', $url);
        $path = substr($path, 0, strcspn($path, '?#'));
        if (preg_match(self::MEDIA_SEGMENT_PATTERN, $path, $match) !== 1) {
            return null;
        }

        return $this->normaliser->normalise($this->percentDecode($match[1]));
    }

    /**
     * Decode percent-escapes of a URL path; a `+` stays a plus sign, as it does in a path
     *
     * @param string $path
     * @return string
     */
    private function percentDecode(string $path): string
    {
        return urldecode(str_replace('+', '%2B', $path));
    }
}
