<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSlider\Model\Data\MediaRelativePath;

/**
 * The media paths the slider data referenced at one moment, answering "is this file still in use?" without a query.
 *
 * Paths are media-relative and compared in their canonical form on both sides: a leading `/`, doubled slashes and `.`
 * segments do not matter, so a stored `banner_slider/image//a.jpg` protects the file `banner_slider/image/a.jpg`. A
 * value that is not a safe media path is compared as it is, after trimming.
 */
class MediaReferenceSnapshot
{
    /**
     * @var array<string, true>
     */
    private readonly array $paths;

    /**
     * @param list<string> $paths Media-relative paths
     */
    public function __construct(array $paths)
    {
        $index = [];
        foreach ($paths as $path) {
            $key = $this->key($path);
            if ($key !== '') {
                $index[$key] = true;
            }
        }
        $this->paths = $index;
    }

    /**
     * Whether any row referenced the path
     *
     * @param string $path Media-relative path
     * @return bool
     */
    public function isReferenced(string $path): bool
    {
        return isset($this->paths[$this->key($path)]);
    }

    /**
     * Every referenced path, sorted
     *
     * @return list<string>
     */
    public function getPaths(): array
    {
        $paths = array_map('strval', array_keys($this->paths));
        sort($paths);

        return $paths;
    }

    /**
     * The lookup form of a path
     *
     * @param string $path
     * @return string
     */
    private function key(string $path): string
    {
        $trimmed = ltrim(trim($path), '/');
        try {
            return (new MediaRelativePath($trimmed))->toCanonicalString();
        } catch (\InvalidArgumentException) {
            return $trimmed;
        }
    }
}
