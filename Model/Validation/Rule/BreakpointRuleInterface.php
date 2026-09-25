<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule;

use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Magento\Framework\Phrase;

/**
 * One rule a breakpoint must meet before it is saved; the breakpoint validator runs every rule registered in its
 * `di.xml` pool.
 *
 * A rule reports what is wrong instead of throwing, so the validator can show the admin every broken rule at once.
 */
interface BreakpointRuleInterface
{
    /**
     * Messages describing how the breakpoint breaks the rule; empty when it meets it
     *
     * @param BreakpointInterface $breakpoint
     * @return list<Phrase>
     */
    public function validate(BreakpointInterface $breakpoint): array;
}
