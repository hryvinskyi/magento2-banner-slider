<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Banner;

/**
 * Which URLs a banner accepts, so no stored link or video source can run script in the visitor's browser.
 *
 * - A link may use `http:`, `https:`, `mailto:` or `tel:`, or be relative: starting with `/`, `#` or `?`, or
 *   carrying no scheme at all.
 * - A video URL must be an absolute `http:` or `https:` URL.
 *
 * Browsers ignore whitespace and control characters inside a scheme (`java\tscript:`), so any URL containing
 * them, or starting or ending with whitespace, is rejected before the scheme is read.
 */
class BannerUrlPolicy
{
    private const LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];
    private const VIDEO_SCHEMES = ['http', 'https'];
    private const SCHEME_PATTERN = '/^([A-Za-z][A-Za-z0-9+.-]*):/';
    private const UNSAFE_CHARACTERS = '/[\x00-\x1F\x7F]/';

    /**
     * Reject a link URL outside the allowed schemes
     *
     * @param string $url
     * @return void
     * @throws \InvalidArgumentException When the URL has another scheme or contains unsafe characters
     */
    public function assertLinkUrl(string $url): void
    {
        $this->assertClean('Banner link URL', $url);
        $scheme = $this->schemeOf($url);
        if ($scheme !== null && !in_array($scheme, self::LINK_SCHEMES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Banner link URL must use http, https, mailto or tel, or be relative; got the scheme "%s".',
                $scheme
            ));
        }
    }

    /**
     * Reject a video URL that is not an absolute http(s) URL
     *
     * @param string $url
     * @return void
     * @throws \InvalidArgumentException When the URL is not an http or https URL
     */
    public function assertVideoUrl(string $url): void
    {
        $this->assertClean('Banner video URL', $url);
        $scheme = $this->schemeOf($url);
        if ($scheme === null || !in_array($scheme, self::VIDEO_SCHEMES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Banner video URL must be an http or https URL, got "%s".', $url)
            );
        }
    }

    /**
     * Reject an empty URL, surrounding whitespace and control characters
     *
     * @param string $label
     * @param string $url
     * @return void
     * @throws \InvalidArgumentException
     */
    private function assertClean(string $label, string $url): void
    {
        if ($url === '') {
            throw new \InvalidArgumentException(sprintf('%s must not be empty; use null for none.', $label));
        }
        if (trim($url) !== $url || preg_match(self::UNSAFE_CHARACTERS, $url) === 1) {
            throw new \InvalidArgumentException(
                sprintf('%s must not contain control characters or surrounding whitespace.', $label)
            );
        }
    }

    /**
     * Lowercase scheme of the URL, or null when it has none
     *
     * @param string $url
     * @return string|null
     */
    private function schemeOf(string $url): ?string
    {
        if (preg_match(self::SCHEME_PATTERN, $url, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }
}
