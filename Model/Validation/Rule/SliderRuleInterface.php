<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Phrase;

/**
 * One rule a slider must meet before it is saved; the slider validator runs every rule registered in its
 * `di.xml` pool.
 *
 * A rule reports what is wrong instead of throwing, so the validator can show the admin every broken rule at once.
 */
interface SliderRuleInterface
{
    /**
     * Messages describing how the slider breaks the rule; empty when it meets it
     *
     * @param SliderInterface $slider
     * @return list<Phrase>
     */
    public function validate(SliderInterface $slider): array;
}
