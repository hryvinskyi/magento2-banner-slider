<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule\Banner;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\BannerRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;

/**
 * A banner is saved as part of a slider.
 */
class SliderAssigned implements BannerRuleInterface
{
    /**
     * @inheritDoc
     */
    public function validate(BannerInterface $banner): array
    {
        return $banner->getSliderId() === null ? [__('Choose the slider the banner belongs to.')] : [];
    }
}
