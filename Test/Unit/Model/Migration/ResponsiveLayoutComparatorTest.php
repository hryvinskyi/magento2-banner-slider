<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Migration\ResponsiveLayoutComparator;
use Hryvinskyi\BannerSliderApi\Api\Value\ResponsiveItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponsiveLayoutComparator::class)]
class ResponsiveLayoutComparatorTest extends TestCase
{
    /**
     * Lists that differ as data but not in layout are the same layout
     *
     * @return void
     */
    public function testSameLayoutDespiteDifferentData(): void
    {
        $comparator = new ResponsiveLayoutComparator();

        self::assertTrue($comparator->isSameLayout(
            [new ResponsiveItem(0, 1, null), new ResponsiveItem(768, 1, null)],
            [new ResponsiveItem(0, 1, null)]
        ));
        self::assertTrue($comparator->isSameLayout([], [new ResponsiveItem(0, 1, '0px')]));
        self::assertTrue($comparator->isSameLayout(
            [new ResponsiveItem(600, 2, '4px'), new ResponsiveItem(0, 1, null)],
            [new ResponsiveItem(0, 1, null), new ResponsiveItem(600, 2, '4px'), new ResponsiveItem(900, 2, null)]
        ));
    }

    /**
     * A different slide count or gap at any width is a different layout
     *
     * @return void
     */
    public function testDifferentLayouts(): void
    {
        $comparator = new ResponsiveLayoutComparator();

        self::assertFalse($comparator->isSameLayout(
            [new ResponsiveItem(0, 1, null), new ResponsiveItem(768, 3, null)],
            [new ResponsiveItem(0, 3, null), new ResponsiveItem(769, 1, null)]
        ));
        self::assertFalse($comparator->isSameLayout([new ResponsiveItem(0, 2, null)], []));
        self::assertFalse($comparator->isSameLayout(
            [new ResponsiveItem(0, 1, '4px')],
            [new ResponsiveItem(0, 1, '8px')]
        ));
    }
}
