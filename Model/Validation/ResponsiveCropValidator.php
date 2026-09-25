<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCropRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Validation\ResponsiveCropValidatorInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;

/**
 * Runs every crop rule of its `di.xml` pool and reports all broken rules in one exception.
 *
 * Adding a rule means adding a class and a pool item; this class never changes for it. A pool item that is not a
 * crop rule fails when the validator is built, not silently at save time.
 */
class ResponsiveCropValidator implements ResponsiveCropValidatorInterface
{
    /**
     * @var list<ResponsiveCropRuleInterface>
     */
    private readonly array $rules;

    /**
     * @param array<mixed> $rules ResponsiveCrop rules, keyed by name
     * @throws \InvalidArgumentException When an item is not a crop rule
     */
    public function __construct(array $rules = [])
    {
        $accepted = [];
        foreach ($rules as $name => $rule) {
            if (!$rule instanceof ResponsiveCropRuleInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The crop validation rule "%s" must implement %s.',
                    $name,
                    ResponsiveCropRuleInterface::class
                ));
            }
            $accepted[] = $rule;
        }
        $this->rules = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function validate(ResponsiveCropInterface $crop): void
    {
        $errors = [];
        foreach ($this->rules as $rule) {
            array_push($errors, ...$rule->validate($crop));
        }
        if ($errors === []) {
            return;
        }

        $messages = array_map(fn (Phrase $error): string => $error->render(), $errors);
        throw new ValidationException(
            __('The crop is not valid: %1', implode(' ', $messages)),
            null,
            0,
            new ValidationResult($errors)
        );
    }
}
