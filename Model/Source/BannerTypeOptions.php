<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Source;

use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Every banner type, valued by its stored number and labelled with its translated label.
 */
class BannerTypeOptions implements OptionSourceInterface
{
    /**
     * Banner type options for select fields and grid filters
     *
     * @return list<array{value: int, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (BannerType::cases() as $type) {
            $options[] = ['value' => $type->value, 'label' => __($type->label())];
        }

        return $options;
    }
}
