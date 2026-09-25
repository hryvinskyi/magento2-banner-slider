<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule\Breakpoint;

use Hryvinskyi\BannerSlider\Model\Breakpoint;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\BreakpointRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;

/**
 * A breakpoint's target width and height are at most 5000 pixels, so a crop never has to be rendered at a size that
 * would exhaust a request's memory.
 *
 * The setters guard new values; this rule catches a stored row that predates the limit.
 */
class TargetSizeWithinLimit implements BreakpointRuleInterface
{
    /**
     * @inheritDoc
     */
    public function validate(BreakpointInterface $breakpoint): array
    {
        $errors = [];
        if ($breakpoint->getTargetWidth() > Breakpoint::MAX_TARGET_SIZE) {
            $errors[] = __(
                'The target width of breakpoint "%1" must be at most %2 pixels.',
                $breakpoint->getIdentifier(),
                Breakpoint::MAX_TARGET_SIZE
            );
        }
        if (($breakpoint->getTargetHeight() ?? 0) > Breakpoint::MAX_TARGET_SIZE) {
            $errors[] = __(
                'The target height of breakpoint "%1" must be at most %2 pixels.',
                $breakpoint->getIdentifier(),
                Breakpoint::MAX_TARGET_SIZE
            );
        }

        return $errors;
    }
}
