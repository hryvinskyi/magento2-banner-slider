<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Validation\Rule\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCrop\VariantFormatsRegistered;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariantFormatsRegistered::class)]
class VariantFormatsRegisteredTest extends TestCase
{
    /**
     * Only variant formats of the registry pass; each other format is reported
     *
     * @param list<string> $formats
     * @param int $errors
     * @return void
     */
    #[TestWith([[], 0])]
    #[TestWith([['webp', 'avif'], 0])]
    #[TestWith([['png'], 1])]
    #[TestWith([['jxl', 'webp', 'heic'], 2])]
    public function testVariantFormats(array $formats, int $errors): void
    {
        $registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('getVariantFormats')->willReturn([
            new ImageFormat('avif', 'image/avif', 'avif'),
            new ImageFormat('webp', 'image/webp', 'webp'),
        ]);
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->method('getBreakpointId')->willReturn(4);
        $crop->method('getVariants')->willReturn(
            array_map(static fn (string $format): CropVariant => new CropVariant($format, 80), $formats)
        );

        self::assertCount($errors, (new VariantFormatsRegistered($registry))->validate($crop));
    }
}
