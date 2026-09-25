<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropTargetSize::class)]
class CropTargetSizeTest extends TestCase
{
    /**
     * The width is the target width; the height is the target height or follows the rectangle's ratio
     *
     * @param int $targetWidth
     * @param int|null $targetHeight
     * @param int $rectWidth
     * @param int $rectHeight
     * @param int $expectedHeight
     * @return void
     */
    #[TestWith([1920, 600, 1000, 1000, 600])]
    #[TestWith([1920, null, 1600, 900, 1080])]
    #[TestWith([767, null, 1000, 333, 255])]
    #[TestWith([100, null, 3000, 1, 1])]
    public function testResolve(
        int $targetWidth,
        ?int $targetHeight,
        int $rectWidth,
        int $rectHeight,
        int $expectedHeight
    ): void {
        $size = (new CropTargetSize())->resolve(
            new BreakpointSpec('desktop', '(min-width: 1200px)', 1200, $targetWidth, $targetHeight),
            new CropRect(5, 5, $rectWidth, $rectHeight)
        );

        self::assertSame($targetWidth, $size->getWidth());
        self::assertSame($expectedHeight, $size->getHeight());
    }

    /**
     * A breakpoint entity is read through its rendering view
     *
     * @return void
     */
    public function testResolveFromBreakpointEntity(): void
    {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('toSpec')->willReturn(new BreakpointSpec('tablet', '(min-width: 768px)', 768, 992, null));

        $size = (new CropTargetSize())->resolve($breakpoint, new CropRect(0, 0, 800, 400));

        self::assertSame([992, 496], [$size->getWidth(), $size->getHeight()]);
    }

    /**
     * Without a rectangle only a breakpoint that fixes both sides yields a size
     *
     * @return void
     */
    public function testResolveWithoutRect(): void
    {
        $size = new CropTargetSize();

        $fixed = $size->resolveWithoutRect(new BreakpointSpec('mobile', '(max-width: 767px)', 0, 767, 500));
        self::assertNotNull($fixed);
        self::assertSame([767, 500], [$fixed->getWidth(), $fixed->getHeight()]);
        self::assertNull($size->resolveWithoutRect(new BreakpointSpec('mobile', '(max-width: 767px)', 0, 767, null)));
    }
}
