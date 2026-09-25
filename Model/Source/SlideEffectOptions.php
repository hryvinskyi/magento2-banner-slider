<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Source;

use Hryvinskyi\BannerSliderApi\Api\Value\SlideEffect;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Every slide transition, valued by its stored code.
 */
class SlideEffectOptions implements OptionSourceInterface
{
    /**
     * Slide effect options for select fields
     *
     * @return list<array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (SlideEffect::cases() as $effect) {
            $options[] = ['value' => $effect->value, 'label' => $this->label($effect)];
        }

        return $options;
    }

    /**
     * Translated label of an effect
     *
     * @param SlideEffect $effect
     * @return Phrase
     */
    private function label(SlideEffect $effect): Phrase
    {
        return match ($effect) {
            SlideEffect::SLIDE => __('Slide'),
            SlideEffect::FADE => __('Fade'),
        };
    }
}
