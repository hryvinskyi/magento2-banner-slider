<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\Encoder\ImageEncoderInterface;
use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\ImageConverter;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ImageConverter::class)]
class ImageConverterTest extends TestCase
{
    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var LocalFileDriver&MockObject
     */
    private MockObject $localDriver;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->localDriver = $this->createMock(LocalFileDriver::class);
        $this->localDriver->method('stat')->willReturn(['size' => 42]);
    }

    /**
     * An unavailable first encoder is skipped and the next available one encodes
     *
     * @return void
     */
    public function testFirstUnavailableIsSkipped(): void
    {
        $first = $this->encoder(false);
        $first->expects(self::never())->method('encode');
        $second = $this->encoder(true);
        $second->expects(self::once())->method('encode')->with('/tmp/in.png', '/tmp/out.webp', 85);

        $this->converter(['gd' => $first, 'cwebp' => $second])
            ->convert('/tmp/in.png', $this->webp(), 85, '/tmp/out.webp');
    }

    /**
     * When the first encoder fails, the failure is logged and the next one is tried
     *
     * @return void
     */
    public function testFirstFailsThenSecondSucceeds(): void
    {
        $failure = new EncodingException(__('GD could not decode the source image.'));
        $first = $this->encoder(true);
        $first->method('encode')->willThrowException($failure);
        $second = $this->encoder(true);
        $second->expects(self::once())->method('encode');
        $this->logger->expects(self::once())->method('warning')
            ->with(self::stringContains('"gd"'), ['exception' => $failure]);

        $this->converter(['gd' => $first, 'cwebp' => $second])
            ->convert('/tmp/in.png', $this->webp(), 85, '/tmp/out.webp');
    }

    /**
     * An encoder that reports success but leaves no file counts as failed
     *
     * @return void
     */
    public function testMissingOutputCountsAsFailure(): void
    {
        $localDriver = $this->createMock(LocalFileDriver::class);
        $localDriver->method('stat')->willThrowException(new FileSystemException(__('Cannot gather stats!')));
        $converter = new ImageConverter($this->logger, $localDriver, ['webp' => ['gd' => $this->encoder(true)]]);

        $this->expectException(EncodingException::class);
        $converter->convert('/tmp/in.png', $this->webp(), 85, '/tmp/out.webp');
    }

    /**
     * With every encoder failing, the conversion fails with the last failure as its cause
     *
     * @return void
     */
    public function testAllFail(): void
    {
        $last = new EncodingException(__('The cwebp encoder could not encode the image as webp.'));
        $first = $this->encoder(true);
        $first->method('encode')->willThrowException(new EncodingException(__('GD failed.')));
        $second = $this->encoder(true);
        $second->method('encode')->willThrowException($last);

        try {
            $this->converter(['gd' => $first, 'cwebp' => $second])
                ->convert('/tmp/in.png', $this->webp(), 85, '/tmp/out.webp');
            self::fail('The conversion should have failed.');
        } catch (EncodingException $e) {
            self::assertSame('The image could not be encoded as webp.', $e->getMessage());
            self::assertSame($last, $e->getPrevious());
        }
    }

    /**
     * With no encoder available the format is not encodable and a conversion fails
     *
     * @return void
     */
    public function testNoneAvailable(): void
    {
        $converter = $this->converter(['gd' => $this->encoder(false), 'cwebp' => $this->encoder(false)]);

        self::assertFalse($converter->isEncodable($this->webp()));
        self::assertFalse($converter->isEncodable(new ImageFormat('avif', 'image/avif', 'avif')));

        $this->expectException(EncodingException::class);
        $converter->convert('/tmp/in.png', $this->webp(), 85, '/tmp/out.webp');
    }

    /**
     * One available encoder makes the format encodable
     *
     * @return void
     */
    public function testEncodable(): void
    {
        self::assertTrue($this->converter(['gd' => $this->encoder(false), 'cwebp' => $this->encoder(true)])
            ->isEncodable($this->webp()));
    }

    /**
     * An encoder registered under a format it does not write fails when the converter is built
     *
     * @return void
     */
    public function testMisregisteredEncoderFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ImageConverter($this->logger, $this->localDriver, ['avif' => ['cwebp' => $this->encoder(true)]]);
    }

    /**
     * A converter with WebP encoders
     *
     * @param array<string,ImageEncoderInterface> $encoders
     * @return ImageConverter
     */
    private function converter(array $encoders): ImageConverter
    {
        return new ImageConverter($this->logger, $this->localDriver, ['webp' => $encoders]);
    }

    /**
     * A WebP encoder stub
     *
     * @param bool $available
     * @return ImageEncoderInterface&MockObject
     */
    private function encoder(bool $available): ImageEncoderInterface&MockObject
    {
        $encoder = $this->createMock(ImageEncoderInterface::class);
        $encoder->method('getFormatCode')->willReturn('webp');
        $encoder->method('isAvailable')->willReturn($available);

        return $encoder;
    }

    /**
     * The WebP format
     *
     * @return ImageFormat
     */
    private function webp(): ImageFormat
    {
        return new ImageFormat('webp', 'image/webp', 'webp');
    }
}
