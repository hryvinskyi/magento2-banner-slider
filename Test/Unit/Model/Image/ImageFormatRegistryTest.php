<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\ImageConverter;
use Hryvinskyi\BannerSlider\Model\Image\ImageFormatRegistry;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageFormatRegistry::class)]
class ImageFormatRegistryTest extends TestCase
{
    /**
     * Formats are found by code and by MIME type
     *
     * @return void
     */
    public function testLookupByCodeAndMimeType(): void
    {
        $registry = $this->registry();

        self::assertTrue($registry->has('webp'));
        self::assertFalse($registry->has('bmp'));
        self::assertTrue($registry->get('jpeg')->equals(new ImageFormat('jpeg', 'image/jpeg', 'jpg')));
        self::assertSame('avif', $registry->getByMimeType('IMAGE/AVIF')?->getCode());
        self::assertNull($registry->getByMimeType('image/svg+xml'));
    }

    /**
     * An unknown code fails loudly
     *
     * @return void
     */
    public function testUnknownCodeFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->registry()->get('bmp');
    }

    /**
     * Extensions match the canonical one and the aliases, in any case and with or without the dot
     *
     * @param string $extension
     * @param string|null $code
     * @return void
     */
    #[TestWith(['jpg', 'jpeg'])]
    #[TestWith(['JPG', 'jpeg'])]
    #[TestWith(['jpeg', 'jpeg'])]
    #[TestWith(['Jpeg', 'jpeg'])]
    #[TestWith(['jpe', 'jpeg'])]
    #[TestWith(['.png', 'png'])]
    #[TestWith(['WEBP', 'webp'])]
    #[TestWith(['avif', 'avif'])]
    #[TestWith(['svg', null])]
    #[TestWith(['', null])]
    public function testGetByExtension(string $extension, ?string $code): void
    {
        self::assertSame($code, $this->registry()->getByExtension($extension)?->getCode());
    }

    /**
     * Variant formats come in preference order, most preferred first
     *
     * @return void
     */
    public function testVariantFormatsInPreferenceOrder(): void
    {
        $codes = array_map(
            static fn (ImageFormat $format): string => $format->getCode(),
            $this->registry()->getVariantFormats()
        );

        self::assertSame(['avif', 'webp'], $codes);
    }

    /**
     * Whether a format is encodable is the converter's answer
     *
     * @return void
     */
    public function testIsEncodableAsksTheConverter(): void
    {
        $converter = $this->createMock(ImageConverter::class);
        $converter->method('isEncodable')
            ->willReturnCallback(static fn (ImageFormat $format): bool => $format->getCode() === 'webp');
        $registry = new ImageFormatRegistry($converter, $this->definitions());

        self::assertTrue($registry->isEncodable($registry->get('webp')));
        self::assertFalse($registry->isEncodable($registry->get('avif')));
    }

    /**
     * Two formats claiming one extension fail when the registry is built
     *
     * @return void
     */
    public function testDuplicateExtensionFails(): void
    {
        $definitions = $this->definitions();
        $definitions['png']['aliases'] = ['jpeg'];

        $this->expectException(\InvalidArgumentException::class);
        new ImageFormatRegistry($this->createMock(ImageConverter::class), $definitions);
    }

    /**
     * Incomplete definitions fail when the registry is built
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    #[TestWith(['mime', null])]
    #[TestWith(['extension', ''])]
    #[TestWith(['aliases', 'jpeg'])]
    #[TestWith(['aliases', [3]])]
    #[TestWith(['preference', 'high'])]
    public function testBrokenDefinitionFails(string $key, mixed $value): void
    {
        $definitions = $this->definitions();
        $definitions['webp'][$key] = $value;

        $this->expectException(\InvalidArgumentException::class);
        new ImageFormatRegistry($this->createMock(ImageConverter::class), $definitions);
    }

    /**
     * The registry with the package's format definitions, shaped as `di.xml` passes them
     *
     * @return ImageFormatRegistry
     */
    private function registry(): ImageFormatRegistry
    {
        return new ImageFormatRegistry($this->createMock(ImageConverter::class), $this->definitions());
    }

    /**
     * The package's format definitions
     *
     * @return array<string,array<string,mixed>>
     */
    private function definitions(): array
    {
        return [
            'jpeg' => [
                'mime' => 'image/jpeg',
                'extension' => 'jpg',
                'aliases' => ['jpeg' => 'jpeg', 'jpe' => 'jpe'],
                'variant' => false,
            ],
            'png' => ['mime' => 'image/png', 'extension' => 'png', 'variant' => false],
            'gif' => ['mime' => 'image/gif', 'extension' => 'gif'],
            'webp' => ['mime' => 'image/webp', 'extension' => 'webp', 'variant' => true, 'preference' => '10'],
            'avif' => ['mime' => 'image/avif', 'extension' => 'avif', 'variant' => true, 'preference' => 20],
        ];
    }
}
