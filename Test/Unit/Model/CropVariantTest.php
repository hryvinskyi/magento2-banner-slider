<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropVariant::class)]
class CropVariantTest extends TestCase
{
    /**
     * A valid variant keeps its values
     *
     * @return void
     */
    public function testValues(): void
    {
        $variant = new CropVariant('webp', 85, 'banner_slider/responsive/1/desktop_abc.webp');

        self::assertSame('webp', $variant->getFormat());
        self::assertSame(85, $variant->getQuality());
        self::assertSame('banner_slider/responsive/1/desktop_abc.webp', $variant->getPath());
    }

    /**
     * Setting a path returns a new variant and leaves the original unchanged
     *
     * @return void
     */
    public function testWithPathIsImmutable(): void
    {
        $variant = new CropVariant('avif', 80);

        $generated = $variant->withPath('banner_slider/responsive/1/desktop_abc.avif');

        self::assertNotSame($variant, $generated);
        self::assertNull($variant->getPath());
        self::assertSame('banner_slider/responsive/1/desktop_abc.avif', $generated->getPath());
        self::assertSame('avif', $generated->getFormat());
        self::assertSame(80, $generated->getQuality());
    }

    /**
     * Values that break a rule are rejected
     *
     * @param string $format
     * @param int $quality
     * @param string|null $path
     * @return void
     */
    #[TestWith(['WEBP', 85, null])]
    #[TestWith(['w', 85, null])]
    #[TestWith(['webp', 0, null])]
    #[TestWith(['webp', 101, null])]
    #[TestWith(['webp', 85, '/abs/path.webp'])]
    #[TestWith(['webp', 85, ''])]
    public function testInvalidValuesAreRejected(string $format, int $quality, ?string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CropVariant($format, $quality, $path);
    }
}
