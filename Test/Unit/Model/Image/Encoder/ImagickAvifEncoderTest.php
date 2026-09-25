<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image\Encoder;

use Hryvinskyi\BannerSlider\Model\Image\Encoder\ImagickAvifEncoder;
use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\ImagickCreator;
use Hryvinskyi\BannerSlider\Model\Image\RuntimeCapabilities;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImagickAvifEncoder::class)]
class ImagickAvifEncoderTest extends TestCase
{
    /**
     * @var \Imagick&MockObject
     */
    private MockObject $imagick;

    /**
     * @var ImagickAvifEncoder
     */
    private ImagickAvifEncoder $encoder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        if (!class_exists(\Imagick::class)) {
            self::markTestSkipped('The Imagick extension is not loaded.');
        }
        $this->imagick = $this->createMock(\Imagick::class);
        $creator = $this->createMock(ImagickCreator::class);
        $creator->method('create')->willReturn($this->imagick);
        $runtime = $this->createMock(RuntimeCapabilities::class);
        $runtime->method('imagickSupports')->with('AVIF')->willReturn(true);
        $this->encoder = new ImagickAvifEncoder($runtime, $creator);
    }

    /**
     * The image is read, converted at the clamped quality, written, and the object released
     *
     * @return void
     */
    public function testEncode(): void
    {
        $this->imagick->expects(self::once())->method('readImage')->with('/tmp/in.png')->willReturn(true);
        $this->imagick->expects(self::once())->method('setImageFormat')->with('AVIF')->willReturn(true);
        $this->imagick->expects(self::once())->method('setImageCompressionQuality')->with(100)->willReturn(true);
        $this->imagick->expects(self::once())->method('writeImage')->with('/tmp/out.avif')->willReturn(true);
        $this->imagick->expects(self::once())->method('clear')->willReturn(true);

        $this->encoder->encode('/tmp/in.png', '/tmp/out.avif', 120);

        self::assertTrue($this->encoder->isAvailable());
        self::assertSame('avif', $this->encoder->getFormatCode());
    }

    /**
     * An Imagick failure becomes an encoding failure, and the object is still released
     *
     * @return void
     */
    public function testImagickFailure(): void
    {
        $failure = new \ImagickException('no decode delegate');
        $this->imagick->method('readImage')->willThrowException($failure);
        $this->imagick->expects(self::once())->method('clear')->willReturn(true);

        try {
            $this->encoder->encode('/tmp/in.gif', '/tmp/out.avif', 80);
            self::fail('The encoder should have failed.');
        } catch (EncodingException $e) {
            self::assertSame($failure, $e->getPrevious());
        }
    }

    /**
     * A write Imagick reports as failed is an encoding failure
     *
     * @return void
     */
    public function testWriteReportedAsFailed(): void
    {
        $this->imagick->method('readImage')->willReturn(true);
        $this->imagick->method('writeImage')->willReturn(false);
        $this->imagick->expects(self::once())->method('clear')->willReturn(true);

        $this->expectException(EncodingException::class);
        $this->encoder->encode('/tmp/in.png', '/tmp/out.avif', 80);
    }
}
