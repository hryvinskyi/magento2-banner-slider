<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule\Breakpoint;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\BreakpointRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointInput;

/**
 * A breakpoint is saved complete: slider, name, a valid identifier, a media query and a target width.
 *
 * The setters guard each value; this rule catches a breakpoint whose field was never set, or a stored row that
 * predates the rules.
 */
class RequiredFields implements BreakpointRuleInterface
{
    /**
     * @inheritDoc
     */
    public function validate(BreakpointInterface $breakpoint): array
    {
        $errors = [];
        if ($breakpoint->getSliderId() === null) {
            $errors[] = __('Choose the slider the breakpoint belongs to.');
        }
        if (trim($breakpoint->getName()) === '') {
            $errors[] = __('Enter the breakpoint name.');
        }
        if (preg_match(BreakpointInput::IDENTIFIER_PATTERN, $breakpoint->getIdentifier()) !== 1) {
            $errors[] = __(
                'The breakpoint identifier "%1" must be 1-50 lowercase letters, digits, "_" or "-", starting with a'
                . ' letter or digit.',
                $breakpoint->getIdentifier()
            );
        }
        if (trim($breakpoint->getMediaQuery()) === '') {
            $errors[] = __('Enter the media query of breakpoint "%1".', $breakpoint->getIdentifier());
        }
        if ($breakpoint->getTargetWidth() < 1) {
            $errors[] = __('The target width of breakpoint "%1" must be greater than 0.', $breakpoint->getIdentifier());
        }

        return $errors;
    }
}
