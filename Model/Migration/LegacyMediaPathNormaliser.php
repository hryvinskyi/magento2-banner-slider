<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Data\MediaRelativePath;

/**
 * Reads a stored media value the way the legacy storefront rendered it, as a path relative to the media directory.
 *
 * - An absolute URL into media (`https://host/pub/media/x/y.jpg`) becomes the part after its `/media/` segment;
 *   uploads could store such a value when the media base URL differed from the one they stripped. When the caller
 *   gives the site's base URLs (the migration does), only a URL on one of their hosts is such a value: a URL on any
 *   other host (a CDN, another site) is not a local file and yields null, so the stored URL is kept. Without them
 *   (the media reference index), every host counts, so no file some stored URL may name is ever taken for unused.
 * - A leading `/` and then a leading `media/` segment are removed.
 * - A local video value is a bare file name that was rendered from `banner_slider/video/`, so it gets that prefix.
 *
 * Paths outside the package's own folders are kept as they are: they are read, never written or deleted. A value
 * that cannot be made a safe media-relative path (a `..` segment, a NUL byte, a backslash, a scheme, an external
 * URL) yields null; the rule is the one of MediaRelativePath.
 */
class LegacyMediaPathNormaliser
{
    /**
     * Folder the legacy storefront rendered bare local video file names from
     */
    public const VIDEO_FOLDER = 'banner_slider/video/';

    private const MEDIA_SEGMENT = 'media/';
    private const MEDIA_URL_PATTERN = '#^(?:https?:)?//([^/?\#]+)/(?:.*?/)?media/(.+)$#i';
    private const HTTP_URL_PATTERN = '#^(?:https?:)?//#i';
    private const AUTHORITY_PATTERN = '#^(?:https?:)?//([^/?\#]+)#i';

    /**
     * Normalise a stored image path
     *
     * @param string $stored
     * @param list<string>|null $siteBaseUrls Base URLs of the site; a media URL on another host is not rewritten.
     *     Null accepts a media URL on any host
     * @return string|null The media-relative path, or null when the value cannot be one
     */
    public function normalise(string $stored, ?array $siteBaseUrls = null): ?string
    {
        $path = trim($stored);
        if (preg_match(self::MEDIA_URL_PATTERN, $path, $matches) === 1) {
            if ($siteBaseUrls !== null && !$this->isSiteHost($matches[1], $siteBaseUrls)) {
                return null;
            }

            return $this->safeOrNull(ltrim($matches[2], '/'));
        }
        if (preg_match(self::HTTP_URL_PATTERN, $path) === 1) {
            return null;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, self::MEDIA_SEGMENT)) {
            $path = substr($path, strlen(self::MEDIA_SEGMENT));
        }

        return $this->safeOrNull($path);
    }

    /**
     * Normalise a stored local video path
     *
     * An absolute URL into media is reduced like an image path; any other URL is not a media path. A local value
     * that is not already under the video folder is placed under it, exactly where the legacy storefront looked.
     *
     * @param string $stored
     * @param list<string>|null $siteBaseUrls Base URLs of the site; a media URL on another host is not rewritten.
     *     Null accepts a media URL on any host
     * @return string|null The media-relative path, or null when the value cannot be one
     */
    public function normaliseVideoPath(string $stored, ?array $siteBaseUrls = null): ?string
    {
        $path = trim($stored);
        if (preg_match(self::HTTP_URL_PATTERN, $path) === 1) {
            return $this->normalise($path, $siteBaseUrls);
        }

        $path = ltrim($path, '/');
        if ($path !== '' && !str_starts_with($path, self::VIDEO_FOLDER)) {
            $path = self::VIDEO_FOLDER . $path;
        }

        return $this->safeOrNull($path);
    }

    /**
     * Whether a URL authority names the host of one of the site's base URLs
     *
     * @param string $authority
     * @param list<string> $siteBaseUrls
     * @return bool
     */
    private function isSiteHost(string $authority, array $siteBaseUrls): bool
    {
        $host = $this->hostOf($authority);
        foreach ($siteBaseUrls as $baseUrl) {
            if (preg_match(self::AUTHORITY_PATTERN, trim($baseUrl), $matches) === 1
                && $this->hostOf($matches[1]) === $host
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The lowercase host of a URL authority, without user information and port
     *
     * @param string $authority
     * @return string
     */
    private function hostOf(string $authority): string
    {
        $at = strrpos($authority, '@');
        $host = $at === false ? $authority : substr($authority, $at + 1);

        return strtolower((string)preg_replace('/:\d*$/', '', $host));
    }

    /**
     * The path itself when it is a safe media-relative path, otherwise null
     *
     * @param string $path
     * @return string|null
     */
    private function safeOrNull(string $path): ?string
    {
        try {
            return (new MediaRelativePath($path))->toString();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
