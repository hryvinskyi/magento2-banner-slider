<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Validation;

use Hryvinskyi\BannerSlider\Model\Validation\BannerValidator;
use Hryvinskyi\BannerSlider\Model\Validation\BreakpointValidator;
use Hryvinskyi\BannerSlider\Model\Validation\ResponsiveCropValidator;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\BannerRuleInterface;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\BreakpointRuleInterface;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCropRuleInterface;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\SliderRuleInterface;
use Hryvinskyi\BannerSlider\Model\Validation\SliderValidator;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SliderValidator::class)]
#[CoversClass(BannerValidator::class)]
#[CoversClass(BreakpointValidator::class)]
#[CoversClass(ResponsiveCropValidator::class)]
class CompositeValidatorsTest extends TestCase
{
    /**
     * Every broken rule of every pool item ends up in one exception
     *
     * @return void
     */
    public function testErrorsOfAllRulesAreCollected(): void
    {
        $first = $this->createMock(SliderRuleInterface::class);
        $first->method('validate')->willReturn([__('First.'), __('Second.')]);
        $second = $this->createMock(SliderRuleInterface::class);
        $second->method('validate')->willReturn([__('Third.')]);
        $validator = new SliderValidator(['first' => $first, 'second' => $second]);

        try {
            $validator->validate($this->createMock(SliderInterface::class));
            self::fail('No validation exception was thrown.');
        } catch (ValidationException $exception) {
            self::assertSame('The slider is not valid: First. Second. Third.', $exception->getMessage());
            $errors = $exception->getErrors();
            self::assertContainsOnlyInstancesOf(LocalizedException::class, $errors);
            self::assertSame(
                ['First.', 'Second.', 'Third.'],
                array_map(fn (LocalizedException $error): string => $error->getMessage(), $errors)
            );
        }
    }

    /**
     * A slider meeting every rule passes
     *
     * @return void
     */
    public function testValidSliderPasses(): void
    {
        $rule = $this->createMock(SliderRuleInterface::class);
        $rule->expects(self::once())->method('validate')->willReturn([]);

        (new SliderValidator(['rule' => $rule]))->validate($this->createMock(SliderInterface::class));
    }

    /**
     * A pool item that is not a rule fails when the validator is built
     *
     * @return void
     */
    public function testForeignPoolItemIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"broken"');

        new SliderValidator(['broken' => new \stdClass()]);
    }

    /**
     * The banner, breakpoint and crop validators collect their rules' errors the same way
     *
     * @return void
     */
    public function testOtherEntityValidators(): void
    {
        $bannerRule = $this->createMock(BannerRuleInterface::class);
        $bannerRule->method('validate')->willReturn([__('Banner.')]);
        $breakpointRule = $this->createMock(BreakpointRuleInterface::class);
        $breakpointRule->method('validate')->willReturn([__('Breakpoint.')]);
        $cropRule = $this->createMock(ResponsiveCropRuleInterface::class);
        $cropRule->method('validate')->willReturn([__('Crop.')]);

        $messages = [];
        $checks = [
            fn () => (new BannerValidator(['r' => $bannerRule]))->validate($this->createMock(BannerInterface::class)),
            fn () => (new BreakpointValidator(['r' => $breakpointRule]))
                ->validate($this->createMock(BreakpointInterface::class)),
            fn () => (new ResponsiveCropValidator(['r' => $cropRule]))
                ->validate($this->createMock(ResponsiveCropInterface::class)),
        ];
        foreach ($checks as $check) {
            try {
                $check();
            } catch (ValidationException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        self::assertSame(
            [
                'The banner is not valid: Banner.',
                'The breakpoint is not valid: Breakpoint.',
                'The crop is not valid: Crop.',
            ],
            $messages
        );
    }
}
