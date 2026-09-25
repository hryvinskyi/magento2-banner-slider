<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Banner;

use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChange;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;

/**
 * A banner save that passed every check: the image size to store, the crop changes to apply, the stored crops that no
 * longer apply, and the slider the stored banner belongs to.
 */
class BannerEditPlan
{
    /**
     * @param Dimensions|null $imageDimensions
     * @param list<CropChange> $cropChanges
     * @param list<ResponsiveCropInterface> $staleCrops Stored crops that no longer apply: made for the breakpoints of
     *     the slider the banner leaves, or cut from the image the save replaces
     * @param int|null $previousSliderId The slider of the stored banner, or null for a new banner
     */
    public function __construct(
        private readonly ?Dimensions $imageDimensions,
        private readonly array $cropChanges,
        private readonly array $staleCrops = [],
        private readonly ?int $previousSliderId = null
    ) {
    }

    /**
     * The size to store with the banner image, read from the file
     *
     * @return Dimensions|null
     */
    public function getImageDimensions(): ?Dimensions
    {
        return $this->imageDimensions;
    }

    /**
     * The checked crop changes
     *
     * @return list<CropChange>
     */
    public function getCropChanges(): array
    {
        return $this->cropChanges;
    }

    /**
     * Stored crops that no longer apply; they are deleted with the save
     *
     * A crop made for a breakpoint of the slider the banner leaves no longer applies, and neither does a crop cut from
     * the banner image the save replaces, unless the same save sends a new crop for its breakpoint.
     *
     * @return list<ResponsiveCropInterface>
     */
    public function getStaleCrops(): array
    {
        return $this->staleCrops;
    }

    /**
     * The slider the stored banner belongs to, or null for a new banner
     *
     * @return int|null
     */
    public function getPreviousSliderId(): ?int
    {
        return $this->previousSliderId;
    }
}
