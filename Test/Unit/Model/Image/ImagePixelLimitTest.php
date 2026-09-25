<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\ImagePixelLimit;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImagePixelLimit::class)]
class ImagePixelLimitTest extends TestCase
{
    /**
     * An image is over the cap only with more pixels than it allows
     *
     * @param int $width
     * @param int $height
     * @param bool $exceeded
     * @return void
     */
    #[TestWith([100, 100, false])]
    #[TestWith([200, 50, false])]
    #[TestWith([10001, 1, true])]
    #[TestWith([101, 100, true])]
    public function testCap(int $width, int $height, bool $exceeded): void
    {
        $limit = new ImagePixelLimit(10000);

        self::assertSame($exceeded, $limit->isExceededBy(new Dimensions($width, $height)));
        self::assertSame(10000, $limit->getMaxPixels());
    }

    /**
     * An image over the cap is refused with a message naming its size and the cap
     *
     * @return void
     */
    public function testAssertProcessable(): void
    {
        $limit = new ImagePixelLimit(10000);
        $limit->assertProcessable(new Dimensions(100, 100));

        $this->expectException(EncodingException::class);
        $this->expectExceptionMessage('The image is 101x100 pixels, more than the 10000 pixels images may have here.');
        $limit->assertProcessable(new Dimensions(101, 100));
    }

    /**
     * The default cap is 50 megapixels
     *
     * @return void
     */
    public function testDefault(): void
    {
        self::assertSame(50000000, (new ImagePixelLimit())->getMaxPixels());
    }

    /**
     * A cap that is not greater than 0 is a configuration error
     *
     * @return void
     */
    public function testRejectsBrokenCap(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ImagePixelLimit(0);
    }
}
