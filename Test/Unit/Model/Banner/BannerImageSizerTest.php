<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Banner;

use Hryvinskyi\BannerSlider\Model\Banner\BannerImageSizer;
use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSlider\Model\Image\MediaImageReader;
use Hryvinskyi\BannerSlider\Test\Unit\Model\ImageFormats;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(BannerImageSizer::class)]
class BannerImageSizerTest extends TestCase
{
    use ImageFormats;

    /**
     * @var MediaImageReader&MockObject
     */
    private MockObject $reader;

    /**
     * @var BannerImageSizer
     */
    private BannerImageSizer $sizer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->reader = $this->createMock(MediaImageReader::class);
        $this->sizer = new BannerImageSizer($this->reader);
    }

    /**
     * A changed image is sized from its file, whatever size the banner carries
     *
     * @return void
     */
    public function testChangedImageIsInspected(): void
    {
        $this->reader->expects(self::once())->method('read')->with('banner_slider/image/new.png')->willReturn(
            new MediaImage('banner_slider/image/new.png', new Dimensions(1920, 1080), $this->format('png'))
        );
        $banner = $this->banner('banner_slider/image/new.png', new Dimensions(1, 1));

        $size = $this->sizer->resolve($banner, $this->banner('banner_slider/image/old.png', new Dimensions(800, 600)));

        self::assertSame([1920, 1080], [$size?->getWidth(), $size?->getHeight()]);
    }

    /**
     * A new banner's image is sized from its file
     *
     * @return void
     */
    public function testNewBannerIsInspected(): void
    {
        $this->reader->method('read')->willReturn(
            new MediaImage('banner_slider/image/new.png', new Dimensions(640, 480), $this->format('png'))
        );

        $size = $this->sizer->resolve($this->banner('banner_slider/image/new.png', null), null);

        self::assertSame(640, $size?->getWidth());
    }

    /**
     * An unchanged, already sized image keeps its stored size without reading the file
     *
     * @return void
     */
    public function testUnchangedImageKeepsStoredSize(): void
    {
        $this->reader->expects(self::never())->method('read');
        $stored = $this->banner('banner_slider/image/a.png', new Dimensions(800, 600));

        $size = $this->sizer->resolve($this->banner('banner_slider/image/a.png', new Dimensions(1, 1)), $stored);

        self::assertSame([800, 600], [$size?->getWidth(), $size?->getHeight()]);
    }

    /**
     * An unchanged image stored without a size is sized now; if its file is gone, it keeps no size
     *
     * @return void
     */
    public function testUnchangedImageWithoutSize(): void
    {
        $stored = $this->banner('legacy_slider/image/a.jpg', null);
        $this->reader->method('read')->willReturnOnConsecutiveCalls(
            new MediaImage('legacy_slider/image/a.jpg', new Dimensions(300, 200), $this->format('jpeg')),
            $this->throwException(new LocalizedException(__('The image "%1" cannot be read.', 'x')))
        );

        $banner = $this->banner('legacy_slider/image/a.jpg', null);

        self::assertSame(300, $this->sizer->resolve($banner, $stored)?->getWidth());
        self::assertNull($this->sizer->resolve($banner, $stored));
    }

    /**
     * A changed image that cannot be read is an error; no image means no size
     *
     * @return void
     */
    public function testUnreadableNewImageAndNoImage(): void
    {
        $this->reader->method('read')->willThrowException(new LocalizedException(__('not an image')));

        self::assertNull($this->sizer->resolve($this->banner(null, null), null));
        $this->expectExceptionMessage('The banner image "banner_slider/image/x.png" cannot be used: not an image');
        $this->sizer->resolve($this->banner('banner_slider/image/x.png', null), null);
    }

    /**
     * A banner with an image and a size
     *
     * @param string|null $image
     * @param Dimensions|null $size
     * @return BannerInterface
     */
    private function banner(?string $image, ?Dimensions $size): BannerInterface
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getImage')->willReturn($image);
        $banner->method('getImageDimensions')->willReturn($size);

        return $banner;
    }
}
