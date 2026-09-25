<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Validation\Rule\Slider;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\Slider\HasStoreScope;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\Slider\NameRequired;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Visibility;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HasStoreScope::class)]
#[CoversClass(NameRequired::class)]
class SliderRulesTest extends TestCase
{
    /**
     * A slider visible somewhere passes the scope rule
     *
     * @return void
     */
    public function testScopedSliderPasses(): void
    {
        self::assertSame([], (new HasStoreScope())->validate($this->slider(new Visibility([0], [], true))));
        self::assertSame([], (new HasStoreScope())->validate($this->slider(new Visibility([1], [2], false))));
    }

    /**
     * An empty store list is refused on save although the visibility itself allows it
     *
     * @return void
     */
    public function testEmptyStoreListIsRefused(): void
    {
        $errors = (new HasStoreScope())->validate($this->slider(new Visibility([], [], true)));

        self::assertSame(['Choose at least one store view for the slider.'], $this->render($errors));
    }

    /**
     * No customer group with the all-groups flag off is refused on save
     *
     * @return void
     */
    public function testNoCustomerGroupIsRefused(): void
    {
        $errors = (new HasStoreScope())->validate($this->slider(new Visibility([], [], false)));

        self::assertSame(
            [
                'Choose at least one store view for the slider.',
                'Choose at least one customer group for the slider, or all customer groups.',
            ],
            $this->render($errors)
        );
    }

    /**
     * A slider needs a name
     *
     * @return void
     */
    public function testNameRequired(): void
    {
        $named = $this->createMock(SliderInterface::class);
        $named->method('getName')->willReturn('Home');
        $unnamed = $this->createMock(SliderInterface::class);
        $unnamed->method('getName')->willReturn(' ');

        self::assertSame([], (new NameRequired())->validate($named));
        self::assertCount(1, (new NameRequired())->validate($unnamed));
    }

    /**
     * A slider double with the visibility
     *
     * @param Visibility $visibility
     * @return SliderInterface
     */
    private function slider(Visibility $visibility): SliderInterface
    {
        $slider = $this->createMock(SliderInterface::class);
        $slider->method('getVisibility')->willReturn($visibility);

        return $slider;
    }

    /**
     * Rendered messages
     *
     * @param list<Phrase> $errors
     * @return list<string>
     */
    private function render(array $errors): array
    {
        return array_map(fn (Phrase $error): string => $error->render(), $errors);
    }
}
