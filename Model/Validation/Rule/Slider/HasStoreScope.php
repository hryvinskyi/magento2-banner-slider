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
 * A slider is saved visible somewhere: at least one store view, and all customer groups or at least one group.
 *
 * Stored sliders may be visible nowhere (a deleted store view or customer group removes its link rows); such a slider
 * keeps loading, but saving it asks for a new scope.
 */
class HasStoreScope implements SliderRuleInterface
{
    /**
     * @inheritDoc
     */
    public function validate(SliderInterface $slider): array
    {
        $visibility = $slider->getVisibility();
        if (!$visibility->isVisibleNowhere()) {
            return [];
        }

        $errors = [];
        if ($visibility->getStoreIds() === []) {
            $errors[] = __('Choose at least one store view for the slider.');
        }
        if (!$visibility->isForAllCustomerGroups() && $visibility->getCustomerGroupIds() === []) {
            $errors[] = __('Choose at least one customer group for the slider, or all customer groups.');
        }

        return $errors;
    }
}
