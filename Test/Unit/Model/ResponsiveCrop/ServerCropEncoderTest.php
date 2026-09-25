<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\CropRenderer;
use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\ImageConverter;
use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\ServerCropEncoder;
use Hryvinskyi\BannerSlider\Test\Unit\Model\ImageFormats;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\FormatRequest;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ServerCropEncoder::class)]
class ServerCropEncoderTest extends TestCase
{
    use ImageFormats;

    /**
     * Every render, conversion and discard, in order
     *
     * @var list<string>
     */
    private array $log = [];

    /**
     * @var bool
     */
    private bool $failConversion = false;

    /**
     * @var ServerCropEncoder
     */
    private ServerCropEncoder $encoder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $renderer = $this->createMock(CropRenderer::class);
        $renderer->method('render')->willReturnCallback(
            function (string $source, CropRect $rect, int $width, ?int $height, ImageFormat $format): string {
                $this->log[] = sprintf('render %s %dx%d as %s', $source, $width, (int)$height, $format->getCode());

                return '/tmp/render.' . $format->getExtension();
            }
        );
        $converter = $this->createMock(ImageConverter::class);
        $converter->method('convert')->willReturnCallback(
            function (string $from, ImageFormat $format, int $quality, string $to): void {
                if ($this->failConversion) {
                    throw new EncodingException(__('The image could not be encoded as %1.', $format->getCode()));
                }
                $this->log[] = sprintf('convert %s to %s at %d', $from, $format->getCode(), $quality);
            }
        );
        $workspace = $this->createMock(LocalFileWorkspace::class);
        $workspace->method('newTempPath')->willReturnCallback(
            static fn (string $extension): string => '/tmp/variant.' . $extension
        );
        $workspace->method('discard')->willReturnCallback(function (string $path): void {
            $this->log[] = 'discard ' . $path;
        });
        $localDriver = $this->createMock(LocalFileDriver::class);
        $localDriver->method('fileGetContents')->willReturnCallback(
            static fn (string $path): string => 'bytes of ' . $path
        );

        $this->encoder = new ServerCropEncoder(
            $renderer,
            $converter,
            $this->formatRegistry(),
            $workspace,
            $localDriver,
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * A PNG original is rendered once and also serves as the base of every variant
     *
     * @return void
     */
    public function testPngOriginalServesVariants(): void
    {
        $bytes = $this->encoder->encode(
            $this->source('png'),
            new CropRect(0, 0, 100, 50),
            new Dimensions(400, 200),
            $this->format('png'),
            [new FormatRequest('avif', 60), new FormatRequest('webp', 80)]
        );

        self::assertSame(
            [
                'png' => 'bytes of /tmp/render.png',
                'avif' => 'bytes of /tmp/variant.avif',
                'webp' => 'bytes of /tmp/variant.webp',
            ],
            $bytes
        );
        self::assertSame(
            [
                'render banner_slider/image/a.png 400x200 as png',
                'convert /tmp/render.png to avif at 60',
                'convert /tmp/render.png to webp at 80',
                'discard /tmp/render.png',
                'discard /tmp/variant.avif',
                'discard /tmp/variant.webp',
            ],
            $this->log
        );
    }

    /**
     * A JPEG original is not the base of a variant: the variants start from a lossless render
     *
     * @return void
     */
    public function testJpegOriginalUsesLosslessBaseForVariants(): void
    {
        $bytes = $this->encoder->encode(
            $this->source('jpeg'),
            new CropRect(0, 0, 100, 50),
            new Dimensions(400, 200),
            $this->format('jpeg'),
            [new FormatRequest('webp', 85)]
        );

        self::assertSame(['jpeg', 'webp'], array_keys($bytes));
        self::assertSame(
            [
                'render banner_slider/image/a.jpeg 400x200 as jpeg',
                'render banner_slider/image/a.jpeg 400x200 as png',
                'convert /tmp/render.png to webp at 85',
                'discard /tmp/render.jpg',
                'discard /tmp/render.png',
                'discard /tmp/variant.webp',
            ],
            $this->log
        );
    }

    /**
     * Without an original to render, only the variant base is rendered
     *
     * @return void
     */
    public function testVariantsOnly(): void
    {
        $bytes = $this->encoder->encode(
            $this->source('jpeg'),
            new CropRect(0, 0, 100, 50),
            new Dimensions(400, 200),
            null,
            [new FormatRequest('webp', 85)]
        );

        self::assertSame(['webp' => 'bytes of /tmp/variant.webp'], $bytes);
        self::assertSame('render banner_slider/image/a.jpeg 400x200 as png', $this->log[0]);
    }

    /**
     * A failed conversion still removes every temp file
     *
     * @return void
     */
    public function testFailureRemovesTempFiles(): void
    {
        $this->failConversion = true;

        try {
            $this->encoder->encode(
                $this->source('png'),
                new CropRect(0, 0, 100, 50),
                new Dimensions(400, 200),
                $this->format('png'),
                [new FormatRequest('webp', 85)]
            );
            self::fail('The conversion failure must surface.');
        } catch (EncodingException) {
            self::assertSame(
                [
                    'render banner_slider/image/a.png 400x200 as png',
                    'discard /tmp/render.png',
                    'discard /tmp/variant.webp',
                ],
                $this->log
            );
        }
    }

    /**
     * A source image in a format
     *
     * @param string $code
     * @return MediaImage
     */
    private function source(string $code): MediaImage
    {
        return new MediaImage('banner_slider/image/a.' . $code, new Dimensions(1000, 500), $this->format($code));
    }
}
