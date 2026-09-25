<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\WholeImageCropArea;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(WholeImageCropArea::class)]
class WholeImageCropAreaTest extends TestCase
{
    /**
     * The area is the largest centred one with the target's aspect ratio, or the whole source for an open height
     *
     * @param int $sourceWidth
     * @param int $sourceHeight
     * @param int $targetWidth
     * @param int|null $targetHeight
     * @param list<int> $expected x, y, width, height
     * @return void
     */
    #[TestWith([3840, 588, 1920, 294, [0, 0, 3840, 588]])]
    #[TestWith([1365, 209, 892, 588, [524, 0, 317, 209]])]
    #[TestWith([400, 300, 892, 588, [0, 18, 400, 264]])]
    #[TestWith([400, 300, 892, null, [0, 0, 400, 300]])]
    public function testCoverArea(
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        ?int $targetHeight,
        array $expected
    ): void {
        $area = (new WholeImageCropArea(new CropTargetSize()))->coverArea(
            new Dimensions($sourceWidth, $sourceHeight),
            new BreakpointSpec('mobile', '(max-width: 767px)', 0, $targetWidth, $targetHeight)
        );

        self::assertSame($expected, [$area->getX(), $area->getY(), $area->getWidth(), $area->getHeight()]);
    }

    /**
     * A crop shows its source as it is when its output is its own source, or the banner image when it has none
     *
     * @param string|null $output
     * @param string|null $source
     * @param string|null $bannerImage
     * @param bool $expected
     * @return void
     */
    #[TestWith(['legacy/m.png', 'legacy/m.png', 'legacy/d.png', true])]
    #[TestWith(['legacy/d.png', null, 'legacy/d.png', true])]
    #[TestWith(['legacy/d.png', '  ', 'legacy/d.png', true])]
    #[TestWith(['legacy/d.png', 'legacy/m.png', 'legacy/d.png', false])]
    #[TestWith(['banner_slider/responsive/1/mobile_a.jpg', 'legacy/m.png', 'legacy/d.png', false])]
    #[TestWith([null, null, null, false])]
    #[TestWith(['', null, '', false])]
    public function testIsSourceAsOutput(?string $output, ?string $source, ?string $bannerImage, bool $expected): void
    {
        self::assertSame(
            $expected,
            (new WholeImageCropArea(new CropTargetSize()))->isSourceAsOutput($output, $source, $bannerImage)
        );
    }
}
