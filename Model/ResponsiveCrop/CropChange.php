<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;

/**
 * One checked crop change of a banner: the desired state, its breakpoint, the stored crop it changes (if any) and,
 * unless the crop is removed, the checked plan of its output files.
 */
class CropChange
{
    /**
     * @param CropInput $input
     * @param BreakpointInterface $breakpoint A breakpoint of the banner's own slider
     * @param ResponsiveCropInterface|null $current The stored crop of the banner for the breakpoint
     * @param CropOutputPlan|null $output Null only when the crop is removed
     */
    public function __construct(
        private readonly CropInput $input,
        private readonly BreakpointInterface $breakpoint,
        private readonly ?ResponsiveCropInterface $current,
        private readonly ?CropOutputPlan $output
    ) {
    }

    /**
     * The desired state
     *
     * @return CropInput
     */
    public function getInput(): CropInput
    {
        return $this->input;
    }

    /**
     * The breakpoint the crop is for
     *
     * @return BreakpointInterface
     */
    public function getBreakpoint(): BreakpointInterface
    {
        return $this->breakpoint;
    }

    /**
     * The stored crop, or null when the change creates one
     *
     * @return ResponsiveCropInterface|null
     */
    public function getCurrent(): ?ResponsiveCropInterface
    {
        return $this->current;
    }

    /**
     * The checked plan of the crop's output files, or null when the crop is removed
     *
     * @return CropOutputPlan|null
     */
    public function getOutput(): ?CropOutputPlan
    {
        return $this->output;
    }
}
