<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Data;

/**
 * A file path the entities store relative to the media directory, and that cannot leave it.
 *
 * This is the one safe-path rule of the package. A valid path is not empty, has no NUL byte and no backslash, is not
 * absolute (no leading `/`, no scheme or drive prefix such as `https:` or `C:`) and has no `..` segment. It may point
 * anywhere inside media: whether core may also write or delete it is decided by the media services.
 *
 * Several spellings name the same file (`a//b.jpg`, `a/./b.jpg`, `a/b.jpg`); the canonical form is the one to compare
 * paths by.
 */
class MediaRelativePath
{
    private const SCHEME_PATTERN = '/^[A-Za-z][A-Za-z0-9+.-]*:/';
    private const PARENT_SEGMENT = '..';
    private const CURRENT_SEGMENT = '.';

    /**
     * @param string $path
     * @throws \InvalidArgumentException When the path is empty, absolute or could leave the media directory
     */
    public function __construct(
        private readonly string $path
    ) {
        if (trim($path) === '') {
            throw new \InvalidArgumentException('A media path must not be empty.');
        }
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('A media path must not contain a NUL byte.');
        }
        if (str_contains($path, '\\')) {
            throw new \InvalidArgumentException(
                sprintf('A media path must use forward slashes only, got "%s".', $path)
            );
        }
        if (str_starts_with($path, '/') || preg_match(self::SCHEME_PATTERN, $path) === 1) {
            throw new \InvalidArgumentException(
                sprintf('A media path must be relative to the media directory, got "%s".', $path)
            );
        }
        if (in_array(self::PARENT_SEGMENT, explode('/', $path), true)) {
            throw new \InvalidArgumentException(
                sprintf('A media path must not contain a ".." segment, got "%s".', $path)
            );
        }
    }

    /**
     * The path as given
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->path;
    }

    /**
     * The path with empty and `.` segments removed, so every spelling of one file compares equal
     *
     * `banner_slider/image//a.jpg`, `banner_slider/./image/a.jpg` and `banner_slider/image/a.jpg/` all give
     * `banner_slider/image/a.jpg`. A path made of nothing but such segments gives an empty string.
     *
     * @return string
     */
    public function toCanonicalString(): string
    {
        return implode('/', array_filter(
            explode('/', trim($this->path)),
            static fn (string $segment): bool => $segment !== '' && $segment !== self::CURRENT_SEGMENT
        ));
    }
}
