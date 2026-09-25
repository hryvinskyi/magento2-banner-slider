<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCropRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;

/**
 * An enabled crop is saved with its crop rectangle; a disabled one may have none yet.
 */
class RectRequiredWhenEnabled implements ResponsiveCropRuleInterface
{
    /**
     * @inheritDoc
     */
    public function validate(ResponsiveCropInterface $crop): array
    {
        if (!$crop->isEnabled() || $crop->getCropRect() !== null) {
            return [];
        }

        return [__('Set the crop area of the enabled crop for breakpoint %1.', $crop->getBreakpointId() ?? 0)];
    }
}
