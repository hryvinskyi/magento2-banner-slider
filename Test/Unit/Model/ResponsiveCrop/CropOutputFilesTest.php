<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputFiles;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Magento\Framework\DataObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropOutputFiles::class)]
class CropOutputFilesTest extends TestCase
{
    /**
     * A crop's output is its original-format file and every generated variant
     *
     * @return void
     */
    public function testOfCrop(): void
    {
        $files = new CropOutputFiles($this->createMock(CollectionFactory::class));

        self::assertSame(
            ['banner_slider/responsive/1/desktop_a.jpg', 'banner_slider/responsive/1/desktop_a.webp'],
            $files->ofCrop($this->crop('banner_slider/responsive/1/desktop_a.jpg', [
                new CropVariant('webp', 85, 'banner_slider/responsive/1/desktop_a.webp'),
                new CropVariant('avif', 80, null),
            ]))
        );
        self::assertSame([], $files->ofCrop($this->crop(null, [])));
    }

    /**
     * The crops of breakpoints are read in one query; no breakpoint, no query
     *
     * @return void
     */
    public function testOfBreakpoints(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('addBreakpointIdsFilter')->with([3, 4])->willReturnSelf();
        $collection->method('getItems')->willReturn([
            $this->crop('banner_slider/responsive/1/tablet_a.jpg', []),
            new DataObject(),
            $this->crop('legacy_slider/image/legacy.jpg', [
                new CropVariant('webp', 85, 'banner_slider/responsive/2/tablet_b.webp'),
            ]),
            $this->crop('banner_slider/responsive/1/tablet_a.jpg', []),
        ]);
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($collection);
        $files = new CropOutputFiles($factory);

        self::assertSame([], $files->ofBreakpoints([]));
        self::assertSame(
            [
                'banner_slider/responsive/1/tablet_a.jpg',
                'legacy_slider/image/legacy.jpg',
                'banner_slider/responsive/2/tablet_b.webp',
            ],
            $files->ofBreakpoints([3, 4])
        );
    }

    /**
     * A crop double
     *
     * @param string|null $croppedImage
     * @param list<CropVariant> $variants
     * @return ResponsiveCropInterface
     */
    private function crop(?string $croppedImage, array $variants): ResponsiveCropInterface
    {
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->method('getCroppedImage')->willReturn($croppedImage);
        $crop->method('getVariants')->willReturn($variants);

        return $crop;
    }
}
