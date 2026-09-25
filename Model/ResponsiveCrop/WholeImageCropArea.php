<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;

/**
 * The crop area of a crop that shows its source image "as it is", and how earlier versions stored such a crop.
 *
 * - **Area:** the largest area with the breakpoint target's aspect ratio, centred in the source (the whole source
 *   when the breakpoint leaves the height open), so the crop is never stretched to the target.
 * - **Earlier storage:** the crop's output file was its source image itself: the crop's own source image, or the
 *   banner image when the crop has none. No crop written by this package has that shape, because every output is
 *   written under the crop output folder, so a crop in it always comes from an earlier version.
 */
class WholeImageCropArea
{
    /**
     * @param CropTargetSize $cropTargetSize
     */
    public function __construct(
        private readonly CropTargetSize $cropTargetSize
    ) {
    }

    /**
     * The whole-image area of a source for a breakpoint
     *
     * @param Dimensions $source Pixel size of the source image
     * @param BreakpointSpec|BreakpointInterface $breakpoint
     * @return CropRect
     */
    public function coverArea(Dimensions $source, BreakpointSpec|BreakpointInterface $breakpoint): CropRect
    {
        return $source->coverRect($this->cropTargetSize->resolveWithoutRect($breakpoint));
    }

    /**
     * Whether a stored crop shows its source as it is: its output file is its source image itself
     *
     * @param string|null $croppedImage The crop's output file
     * @param string|null $sourceImage The crop's own source image; null or blank when it uses the banner image
     * @param string|null $bannerImage
     * @return bool
     */
    public function isSourceAsOutput(?string $croppedImage, ?string $sourceImage, ?string $bannerImage): bool
    {
        $output = $this->nonBlank($croppedImage);
        if ($output === null) {
            return false;
        }

        return $output === ($this->nonBlank($sourceImage) ?? $this->nonBlank($bannerImage));
    }

    /**
     * The value, or null when it is null or blank
     *
     * @param string|null $value
     * @return string|null
     */
    private function nonBlank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
