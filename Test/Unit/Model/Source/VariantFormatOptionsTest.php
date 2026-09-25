<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Source;

use Hryvinskyi\BannerSlider\Model\Source\VariantFormatOptions;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariantFormatOptions::class)]
class VariantFormatOptionsTest extends TestCase
{
    /**
     * The registry's variant formats become options in preference order
     *
     * @return void
     */
    public function testOptionsFollowTheRegistry(): void
    {
        $registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('getVariantFormats')->willReturn([
            new ImageFormat('avif', 'image/avif', 'avif'),
            new ImageFormat('webp', 'image/webp', 'webp'),
        ]);

        self::assertSame(
            [['value' => 'avif', 'label' => 'AVIF'], ['value' => 'webp', 'label' => 'WEBP']],
            (new VariantFormatOptions($registry))->toOptionArray()
        );
    }
}
