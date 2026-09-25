<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule\Slider;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\SliderRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;

/**
 * A slider is saved with a name.
 */
class NameRequired implements SliderRuleInterface
{
    /**
     * @inheritDoc
     */
    public function validate(SliderInterface $slider): array
    {
        return trim($slider->getName()) === '' ? [__('Enter the slider name.')] : [];
    }
}
