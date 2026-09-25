<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

/**
 * Where a crop output file was stored, and whether storing it created the file or found it already there.
 */
class StoredCropFile
{
    /**
     * @param string $path Path relative to the media directory
     * @param bool $created True when this store wrote the file; false when an identical file already existed
     */
    public function __construct(
        private readonly string $path,
        private readonly bool $created
    ) {
    }

    /**
     * Path relative to the media directory
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Whether this store wrote the file
     *
     * @return bool
     */
    public function isCreated(): bool
    {
        return $this->created;
    }
}
