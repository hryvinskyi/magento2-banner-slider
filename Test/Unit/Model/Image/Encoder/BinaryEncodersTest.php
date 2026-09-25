<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image\Encoder;

use Hryvinskyi\BannerSlider\Model\Image\Encoder\AbstractBinaryEncoder;
use Hryvinskyi\BannerSlider\Model\Image\Encoder\CavifEncoder;
use Hryvinskyi\BannerSlider\Model\Image\Encoder\CwebpEncoder;
use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Process\BinaryLocator;
use Hryvinskyi\BannerSlider\Model\Process\ProcessFailedException;
use Hryvinskyi\BannerSlider\Model\Process\ProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractBinaryEncoder::class)]
#[CoversClass(CwebpEncoder::class)]
#[CoversClass(CavifEncoder::class)]
class BinaryEncodersTest extends TestCase
{
    /**
     * @var BinaryLocator&MockObject
     */
    private MockObject $locator;

    /**
     * @var ProcessRunner&MockObject
     */
    private MockObject $runner;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->locator = $this->createMock(BinaryLocator::class);
        $this->locator->method('locate')->willReturnCallback(
            static fn (string $binary): string => '/srv/vendor/bin/' . $binary
        );
        $this->runner = $this->createMock(ProcessRunner::class);
    }

    /**
     * cwebp gets its options, the destination, the end of options and then the source, with the configured timeout
     *
     * @param int $quality
     * @param string $expectedQuality
     * @return void
     */
    #[TestWith([85, '85'])]
    #[TestWith([150, '100'])]
    #[TestWith([0, '1'])]
    public function testCwebpCommand(int $quality, string $expectedQuality): void
    {
        $this->runner->expects(self::once())->method('run')->with(
            [
                '/srv/vendor/bin/cwebp', '-quiet', '-q', $expectedQuality, '-alpha_q', '100', '-m', '6',
                '-segments', '4', '-sns', '80', '-f', '25', '-sharpness', '0', '-strong', '-pass', '10', '-mt',
                '-alpha_method', '1', '-alpha_filter', 'fast', '-o', '/tmp/out.webp', '--', '/tmp/-in.png',
            ],
            120.0
        );

        $encoder = new CwebpEncoder($this->locator, $this->runner, 120.0);
        $encoder->encode('/tmp/-in.png', '/tmp/out.webp', $quality);

        self::assertSame('webp', $encoder->getFormatCode());
        self::assertTrue($encoder->isAvailable());
    }

    /**
     * cavif gets its options, the destination, the end of options and then the source, with the configured timeout
     *
     * @return void
     */
    public function testCavifCommand(): void
    {
        $this->runner->expects(self::once())->method('run')->with(
            [
                '/srv/vendor/bin/cavif', '--quiet', '--overwrite', '--quality', '80', '--output', '/tmp/out.avif',
                '--', '/tmp/in.jpg',
            ],
            180.0
        );

        $encoder = new CavifEncoder($this->locator, $this->runner, 180.0);
        $encoder->encode('/tmp/in.jpg', '/tmp/out.avif', 80);

        self::assertSame('avif', $encoder->getFormatCode());
    }

    /**
     * Without the binary the encoder is unavailable and refuses to encode
     *
     * @return void
     */
    public function testMissingBinary(): void
    {
        $locator = $this->createMock(BinaryLocator::class);
        $locator->method('locate')->willReturn(null);
        $this->runner->expects(self::never())->method('run');
        $encoder = new CwebpEncoder($locator, $this->runner, 120.0);

        self::assertFalse($encoder->isAvailable());
        $this->expectException(EncodingException::class);
        $encoder->encode('/tmp/in.png', '/tmp/out.webp', 85);
    }

    /**
     * A failed run becomes an encoding failure whose message leaves the tool output to the cause
     *
     * @return void
     */
    public function testFailedRun(): void
    {
        $failure = new ProcessFailedException('The command "/srv/vendor/bin/cavif" exited with code 1: bad input');
        $this->runner->method('run')->willThrowException($failure);

        try {
            (new CavifEncoder($this->locator, $this->runner, 180.0))->encode('/tmp/in.gif', '/tmp/out.avif', 80);
            self::fail('The encoder should have failed.');
        } catch (EncodingException $e) {
            self::assertSame('The cavif encoder could not encode the image as avif.', $e->getMessage());
            self::assertSame($failure, $e->getPrevious());
        }
    }
}
