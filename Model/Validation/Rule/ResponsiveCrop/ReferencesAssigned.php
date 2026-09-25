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
 * A crop is saved for a banner and a breakpoint.
 */
class ReferencesAssigned implements ResponsiveCropRuleInterface
{
    /**
     * @inheritDoc
     */
    public function validate(ResponsiveCropInterface $crop): array
    {
        $errors = [];
        if ($crop->getBannerId() === null) {
            $errors[] = __('Choose the banner the crop belongs to.');
        }
        if ($crop->getBreakpointId() === null) {
            $errors[] = __('Choose the breakpoint the crop is made for.');
        }

        return $errors;
    }
}
