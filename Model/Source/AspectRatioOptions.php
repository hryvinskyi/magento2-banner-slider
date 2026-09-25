<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Source;

use Hryvinskyi\BannerSliderApi\Api\Value\AspectRatio;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * The video aspect ratios offered in the admin: 16:9, 4:3, 21:9, 1:1, 9:16 and 3:2, valued and labelled as `W:H`.
 */
class AspectRatioOptions implements OptionSourceInterface
{
    /**
     * Width and height terms of the offered ratios, in display order
     */
    private const PRESETS = [[16, 9], [4, 3], [21, 9], [1, 1], [9, 16], [3, 2]];

    /**
     * Aspect ratio options for select fields
     *
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::PRESETS as [$width, $height]) {
            $ratio = (new AspectRatio($width, $height))->toString();
            $options[] = ['value' => $ratio, 'label' => $ratio];
        }

        return $options;
    }
}
