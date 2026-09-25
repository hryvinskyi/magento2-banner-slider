<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;

/**
 * The largest image, counted in pixels, core accepts as an upload, decodes, or renders.
 *
 * Decoding holds every pixel in memory (about four bytes each), so a small file of huge dimensions could exhaust the
 * memory of a request. The cap comes from `di.xml`.
 */
class ImagePixelLimit
{
    /**
     * @param int $maxPixels Width times height of the largest image processed
     * @throws \InvalidArgumentException When the cap is not greater than 0
     */
    public function __construct(
        private readonly int $maxPixels = 50000000
    ) {
        if ($maxPixels < 1) {
            throw new \InvalidArgumentException(
                sprintf('The image pixel limit must be greater than 0, got %d.', $maxPixels)
            );
        }
    }

    /**
     * The largest pixel count processed
     *
     * @return int
     */
    public function getMaxPixels(): int
    {
        return $this->maxPixels;
    }

    /**
     * Whether an image of the size has more pixels than the cap
     *
     * @param Dimensions $size
     * @return bool
     */
    public function isExceededBy(Dimensions $size): bool
    {
        return $size->getWidth() * $size->getHeight() > $this->maxPixels;
    }

    /**
     * Refuse to process an image of the size when it has more pixels than the cap
     *
     * @param Dimensions $size
     * @return void
     * @throws EncodingException When the image has more pixels than the cap
     */
    public function assertProcessable(Dimensions $size): void
    {
        if (!$this->isExceededBy($size)) {
            return;
        }

        throw new EncodingException(__(
            'The image is %1x%2 pixels, more than the %3 pixels images may have here.',
            $size->getWidth(),
            $size->getHeight(),
            $this->maxPixels
        ));
    }
}
