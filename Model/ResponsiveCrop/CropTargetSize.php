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
 * The pixel size a crop is rendered at for a breakpoint.
 *
 * The width is always the breakpoint's target width. The height is the breakpoint's target height, or, when the
 * breakpoint leaves it open, the height that keeps the crop rectangle's aspect ratio at that width (rounded, at least
 * 1). The size a crop file is encoded at and the size the storefront reserves for it both come from this one rule.
 */
class CropTargetSize
{
    /**
     * The rendered size of a crop rectangle at a breakpoint
     *
     * @param BreakpointSpec|BreakpointInterface $breakpoint
     * @param CropRect $rect
     * @return Dimensions
     */
    public function resolve(BreakpointSpec|BreakpointInterface $breakpoint, CropRect $rect): Dimensions
    {
        $spec = $this->toSpec($breakpoint);
        $width = $spec->getWidth();
        $height = $spec->getHeight()
            ?? max(1, (int)round($width * $rect->getHeight() / $rect->getWidth()));

        return new Dimensions($width, $height);
    }

    /**
     * The rendered size when no crop rectangle is known: the breakpoint target, if it fixes both sides
     *
     * @param BreakpointSpec|BreakpointInterface $breakpoint
     * @return Dimensions|null Null when the breakpoint leaves the height to the crop rectangle
     */
    public function resolveWithoutRect(BreakpointSpec|BreakpointInterface $breakpoint): ?Dimensions
    {
        return $this->toSpec($breakpoint)->getFixedTarget();
    }

    /**
     * The rendering view of a breakpoint
     *
     * @param BreakpointSpec|BreakpointInterface $breakpoint
     * @return BreakpointSpec
     */
    private function toSpec(BreakpointSpec|BreakpointInterface $breakpoint): BreakpointSpec
    {
        return $breakpoint instanceof BreakpointInterface ? $breakpoint->toSpec() : $breakpoint;
    }
}
