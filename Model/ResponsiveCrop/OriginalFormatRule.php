<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;

/**
 * The format of a crop's original-format output, the fallback every browser decodes.
 *
 * A JPEG source gives a JPEG crop; every other source (PNG, GIF, WebP, AVIF) gives a PNG crop, which keeps
 * transparency. The admin cropper's browser encoder follows the same rule, so the files it sends match what the server
 * expects. A requested variant in the original's format adds nothing and is ignored by the crop writer.
 */
class OriginalFormatRule
{
    /**
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param string $photoFormatCode The source format kept as it is
     * @param string $fallbackFormatCode The format of every other source
     */
    public function __construct(
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly string $photoFormatCode = 'jpeg',
        private readonly string $fallbackFormatCode = 'png'
    ) {
    }

    /**
     * The original-format output of a crop cut from a source in the given format
     *
     * @param ImageFormat $sourceFormat
     * @return ImageFormat
     * @throws \InvalidArgumentException When the resulting format is not registered
     */
    public function resolve(ImageFormat $sourceFormat): ImageFormat
    {
        return $this->formatRegistry->get(
            $sourceFormat->getCode() === $this->photoFormatCode ? $this->photoFormatCode : $this->fallbackFormatCode
        );
    }
}
