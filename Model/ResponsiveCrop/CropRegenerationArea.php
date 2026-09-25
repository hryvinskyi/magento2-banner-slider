<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\MediaImageReader;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Magento\Framework\Exception\LocalizedException;

/**
 * The area a stored crop is cut from again when it is regenerated.
 *
 * It is the stored area, except for a crop an earlier version stored as "show the source as it is" (its output file
 * is its source image itself, see WholeImageCropArea): its stored area was only the target size at the top-left
 * corner, so it is re-cut from the whole-image area of its source for its breakpoint. That makes regenerating such a
 * crop right even when its source was missing while the upgrade ran and was copied into media later.
 */
class CropRegenerationArea
{
    /**
     * @param BreakpointRepositoryInterface $breakpointRepository
     * @param MediaImageReader $imageReader
     * @param WholeImageCropArea $wholeImageCropArea
     */
    public function __construct(
        private readonly BreakpointRepositoryInterface $breakpointRepository,
        private readonly MediaImageReader $imageReader,
        private readonly WholeImageCropArea $wholeImageCropArea
    ) {
    }

    /**
     * The area to cut the crop from, or null when it has none
     *
     * @param ResponsiveCropInterface $crop
     * @param string|null $bannerImage The image of the crop's banner
     * @return CropRect|null
     * @throws LocalizedException When the source or the breakpoint of a whole-image crop cannot be read
     * @throws \InvalidArgumentException When the breakpoint of a whole-image crop has no valid size
     */
    public function resolve(ResponsiveCropInterface $crop, ?string $bannerImage): ?CropRect
    {
        $source = $crop->getSourceImage() ?? $bannerImage;
        $breakpointId = $crop->getBreakpointId();
        if ($source === null || $breakpointId === null
            || !$this->wholeImageCropArea->isSourceAsOutput($crop->getCroppedImage(), $source, null)
        ) {
            return $crop->getCropRect();
        }

        return $this->wholeImageCropArea->coverArea(
            $this->imageReader->read($source)->getDimensions(),
            $this->breakpointRepository->getById($breakpointId)
        );
    }
}
