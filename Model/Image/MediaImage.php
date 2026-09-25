<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;

/**
 * An image in the media directory as its bytes describe it: the path, the pixel size and the real format.
 */
class MediaImage
{
    /**
     * @param string $path Safe path relative to the media directory
     * @param Dimensions $dimensions Pixel size read from the file
     * @param ImageFormat $format Format sniffed from the file's bytes
     */
    public function __construct(
        private readonly string $path,
        private readonly Dimensions $dimensions,
        private readonly ImageFormat $format
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
     * Pixel size
     *
     * @return Dimensions
     */
    public function getDimensions(): Dimensions
    {
        return $this->dimensions;
    }

    /**
     * Real format of the file
     *
     * @return ImageFormat
     */
    public function getFormat(): ImageFormat
    {
        return $this->format;
    }
}
