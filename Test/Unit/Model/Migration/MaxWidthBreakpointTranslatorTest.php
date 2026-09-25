<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Migration\MaxWidthBreakpointTranslator;
use Hryvinskyi\BannerSliderApi\Api\Value\ResponsiveItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MaxWidthBreakpointTranslator::class)]
class MaxWidthBreakpointTranslatorTest extends TestCase
{
    /**
     * @var MaxWidthBreakpointTranslator
     */
    private MaxWidthBreakpointTranslator $translator;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->translator = new MaxWidthBreakpointTranslator();
    }

    /**
     * Each setting comes from the smallest matching breakpoint that sets it, per setting
     *
     * @return void
     */
    public function testEachSettingComesFromTheSmallestBreakpointThatSetsIt(): void
    {
        $items = $this->translator->translate([
            1200 => ['perPage' => 4, 'gap' => '20px'],
            480 => ['perPage' => null, 'gap' => '4px'],
            900 => ['perPage' => 3, 'gap' => null],
        ]);

        self::assertSame(
            [[0, 3, '4px'], [481, 3, '20px'], [901, 4, '20px'], [1201, 1, '0px']],
            $this->rows($items)
        );
    }

    /**
     * A range laid out like the one before it is merged into it
     *
     * @return void
     */
    public function testEqualNeighboursMerge(): void
    {
        $items = $this->translator->translate([
            480 => ['perPage' => 2, 'gap' => null],
            768 => ['perPage' => 2, 'gap' => null],
            1024 => ['perPage' => 1, 'gap' => null],
        ]);

        self::assertSame([[0, 2, null], [769, 1, null]], $this->rows($items));
    }

    /**
     * The default gap is stated only where a wider gap has to be undone
     *
     * @return void
     */
    public function testDefaultGapOnlyUndoesAWiderGap(): void
    {
        $items = $this->translator->translate([
            480 => ['perPage' => 2, 'gap' => '8px'],
            768 => ['perPage' => 1, 'gap' => null],
        ]);

        self::assertSame([[0, 2, '8px'], [481, 1, '0px']], $this->rows($items));
    }

    /**
     * A breakpoint at width 0 matches no real screen; alone it gives no rules
     *
     * @return void
     */
    public function testWidthZeroNeverApplies(): void
    {
        self::assertSame([], $this->translator->translate([0 => ['perPage' => 5, 'gap' => '3px']]));
        self::assertSame([], $this->translator->translate([]));
        self::assertSame(
            [[0, 2, null], [769, 1, null]],
            $this->rows($this->translator->translate([
                0 => ['perPage' => 5, 'gap' => null],
                768 => ['perPage' => 2, 'gap' => null],
            ]))
        );
    }

    /**
     * Items as [min width, per page, gap]
     *
     * @param list<ResponsiveItem> $items
     * @return list<array{0: int, 1: int, 2: string|null}>
     */
    private function rows(array $items): array
    {
        return array_map(
            static fn (ResponsiveItem $item): array => [$item->getMinWidth(), $item->getPerPage(), $item->getGap()],
            $items
        );
    }
}
