<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\SliderRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Validation\SliderValidatorInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;

/**
 * Runs every slider rule of its `di.xml` pool and reports all broken rules in one exception.
 *
 * Adding a rule means adding a class and a pool item; this class never changes for it. A pool item that is not a
 * slider rule fails when the validator is built, not silently at save time.
 */
class SliderValidator implements SliderValidatorInterface
{
    /**
     * @var list<SliderRuleInterface>
     */
    private readonly array $rules;

    /**
     * @param array<mixed> $rules Slider rules, keyed by name
     * @throws \InvalidArgumentException When an item is not a slider rule
     */
    public function __construct(array $rules = [])
    {
        $accepted = [];
        foreach ($rules as $name => $rule) {
            if (!$rule instanceof SliderRuleInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The slider validation rule "%s" must implement %s.',
                    $name,
                    SliderRuleInterface::class
                ));
            }
            $accepted[] = $rule;
        }
        $this->rules = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function validate(SliderInterface $slider): void
    {
        $errors = [];
        foreach ($this->rules as $rule) {
            array_push($errors, ...$rule->validate($slider));
        }
        if ($errors === []) {
            return;
        }

        $messages = array_map(fn (Phrase $error): string => $error->render(), $errors);
        throw new ValidationException(
            __('The slider is not valid: %1', implode(' ', $messages)),
            null,
            0,
            new ValidationResult($errors)
        );
    }
}
