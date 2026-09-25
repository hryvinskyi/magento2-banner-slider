<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Config;

use Hryvinskyi\BannerSlider\Model\Config\ImageConfig;
use Hryvinskyi\BannerSlider\Model\Config\UploadLimitReader;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageConfig::class)]
class ImageConfigTest extends TestCase
{
    /**
     * Default formats keep only registered variant formats, in the registry's preference order
     *
     * @param mixed $stored
     * @param list<string> $expected
     * @return void
     */
    #[TestWith(['webp', ['webp']])]
    #[TestWith(['webp,avif', ['avif', 'webp']])]
    #[TestWith([' WEBP , avif ', ['avif', 'webp']])]
    #[TestWith(['webp,bmp,jpeg', ['webp']])]
    #[TestWith(['webp,webp', ['webp']])]
    #[TestWith(['', []])]
    #[TestWith(['bmp', []])]
    #[TestWith([null, ['webp']])]
    #[TestWith([['webp'], ['webp']])]
    public function testDefaultVariantFormats(mixed $stored, array $expected): void
    {
        $config = $this->config(['hryvinskyi_banner_slider/image/default_formats' => $stored]);

        self::assertSame($expected, $config->getDefaultVariantFormats());
    }

    /**
     * A stored quality is rounded and clamped to 1..100; a value that is not a number gives the fallback
     *
     * @param mixed $stored
     * @param int $expected
     * @return void
     */
    #[TestWith(['70', 70])]
    #[TestWith([70, 70])]
    #[TestWith(['72.6', 73])]
    #[TestWith(['0', 1])]
    #[TestWith(['-20', 1])]
    #[TestWith(['250', 100])]
    #[TestWith(['', 85])]
    #[TestWith(['high', 85])]
    #[TestWith([null, 85])]
    public function testQualityClampAndFallback(mixed $stored, int $expected): void
    {
        $config = $this->config(['hryvinskyi_banner_slider/image/webp_quality' => $stored]);

        self::assertSame($expected, $config->getDefaultQuality('webp'));
    }

    /**
     * Each format reads its own quality path
     *
     * @return void
     */
    public function testQualityPerFormat(): void
    {
        $config = $this->config([
            'hryvinskyi_banner_slider/image/webp_quality' => '90',
            'hryvinskyi_banner_slider/image/avif_quality' => '60',
        ]);

        self::assertSame(90, $config->getDefaultQuality('webp'));
        self::assertSame(60, $config->getDefaultQuality('avif'));
    }

    /**
     * A fallback quality outside 1..100 is clamped too
     *
     * @return void
     */
    public function testFallbackQualityIsClamped(): void
    {
        $config = new ImageConfig(
            $this->scopeConfig([]),
            $this->registry(),
            new UploadLimitReader($this->scopeConfig([])),
            ['webp' => '400']
        );

        self::assertSame(100, $config->getDefaultQuality('webp'));
    }

    /**
     * A format without a quality setting is refused
     *
     * @param string $code
     * @return void
     */
    #[TestWith(['jpeg'])]
    #[TestWith(['bmp'])]
    #[TestWith(['../x'])]
    public function testQualityOfUnknownFormat(string $code): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->config([])->getDefaultQuality($code);
    }

    /**
     * The upload cap is read in megabytes, 10 MB when broken
     *
     * @param mixed $stored
     * @param int $expected
     * @return void
     */
    #[TestWith(['10', 10485760])]
    #[TestWith(['2.5', 2621440])]
    #[TestWith(['0', 10485760])]
    #[TestWith(['-1', 10485760])]
    #[TestWith(['lots', 10485760])]
    #[TestWith([null, 10485760])]
    public function testMaxUploadBytes(mixed $stored, int $expected): void
    {
        $config = $this->config(['hryvinskyi_banner_slider/image/max_upload_size_mb' => $stored]);

        self::assertSame($expected, $config->getMaxUploadBytes());
    }

    /**
     * Image settings over the given stored values
     *
     * @param array<string,mixed> $values
     * @return ImageConfig
     */
    private function config(array $values): ImageConfig
    {
        $scopeConfig = $this->scopeConfig($values);

        return new ImageConfig(
            $scopeConfig,
            $this->registry(),
            new UploadLimitReader($scopeConfig),
            ['webp' => '85', 'avif' => '80']
        );
    }

    /**
     * Stored configuration values by path
     *
     * @param array<string,mixed> $values
     * @return ScopeConfigInterface
     */
    private function scopeConfig(array $values): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): mixed => $values[$path] ?? null
        );

        return $scopeConfig;
    }

    /**
     * A registry whose variant formats are AVIF then WebP, and which also knows JPEG
     *
     * @return ImageFormatRegistryInterface
     */
    private function registry(): ImageFormatRegistryInterface
    {
        $registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('getVariantFormats')->willReturn([
            new ImageFormat('avif', 'image/avif', 'avif'),
            new ImageFormat('webp', 'image/webp', 'webp'),
        ]);

        return $registry;
    }
}
