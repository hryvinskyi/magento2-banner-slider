<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;

/**
 * What a change of breakpoint targets does to the crops cut for them, worked out before anything is written: the
 * crops whose area changes (already carrying the new area), and every crop whose files have to be rendered again.
 */
class CropRetargetPlan
{
    /**
     * @param list<ResponsiveCropInterface> $cropsToSave Crops carrying their new area
     * @param list<array{bannerId:int,breakpointId:int}> $regenerations The crops to render again at the new target
     */
    public function __construct(
        private readonly array $cropsToSave = [],
        private readonly array $regenerations = []
    ) {
    }

    /**
     * Crops whose area follows the new target, carrying it
     *
     * @return list<ResponsiveCropInterface>
     */
    public function getCropsToSave(): array
    {
        return $this->cropsToSave;
    }

    /**
     * The crops, by banner and breakpoint, whose files are rendered again once the new targets are committed
     *
     * @return list<array{bannerId: int, breakpointId: int}>
     */
    public function getRegenerations(): array
    {
        return $this->regenerations;
    }
}
