<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image\Encoder;

use Hryvinskyi\BannerSlider\Model\Image\Encoder\AbstractGdEncoder;
use Hryvinskyi\BannerSlider\Model\Image\Encoder\GdAvifEncoder;
use Hryvinskyi\BannerSlider\Model\Image\Encoder\GdPngEncoder;
use Hryvinskyi\BannerSlider\Model\Image\Encoder\GdWebpEncoder;
use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\GdImageDecoder;
use Hryvinskyi\BannerSlider\Model\Image\RuntimeCapabilities;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractGdEncoder::class)]
#[CoversClass(GdWebpEncoder::class)]
#[CoversClass(GdAvifEncoder::class)]
#[CoversClass(GdPngEncoder::class)]
#[CoversClass(GdImageDecoder::class)]
class GdEncodersTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../_files/images/';

    /**
     * @var LocalFileDriver
     */
    private LocalFileDriver $localDriver;

    /**
     * @var list<string>
     */
    private array $outputs = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->localDriver = new LocalFileDriver();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        foreach ($this->outputs as $output) {
            if ($this->localDriver->isExists($output)) {
                $this->localDriver->deleteFile($output);
            }
        }
    }

    /**
     * Each encoder is available only with the decoder, its write function and GD's support for the format
     *
     * @param class-string<AbstractGdEncoder> $encoderClass
     * @param string $writeFunction
     * @param string $gdFeature
     * @param string $formatCode
     * @return void
     */
    #[TestWith([GdWebpEncoder::class, 'imagewebp', 'WebP Support', 'webp'])]
    #[TestWith([GdAvifEncoder::class, 'imageavif', 'AVIF Support', 'avif'])]
    #[TestWith([GdPngEncoder::class, 'imagepng', 'PNG Support', 'png'])]
    public function testAvailabilityFollowsTheRuntime(
        string $encoderClass,
        string $writeFunction,
        string $gdFeature,
        string $formatCode
    ): void {
        $complete = $this->encoder($encoderClass, ['imagecreatefromstring', $writeFunction], [$gdFeature]);
        self::assertTrue($complete->isAvailable());
        self::assertSame($formatCode, $complete->getFormatCode());

        self::assertFalse($this->encoder($encoderClass, ['imagecreatefromstring'], [$gdFeature])->isAvailable());
        self::assertFalse($this->encoder($encoderClass, [$writeFunction], [$gdFeature])->isAvailable());
        self::assertFalse(
            $this->encoder($encoderClass, ['imagecreatefromstring', $writeFunction], [])->isAvailable()
        );
    }

    /**
     * With GD present, each source fixture is encoded into the format
     *
     * @param class-string<AbstractGdEncoder> $encoderClass
     * @param string $source
     * @param string $expectedMime
     * @return void
     */
    #[TestWith([GdWebpEncoder::class, '2x2.png', 'image/webp'])]
    #[TestWith([GdWebpEncoder::class, '2x2.gif', 'image/webp'])]
    #[TestWith([GdAvifEncoder::class, '2x2.jpg', 'image/avif'])]
    #[TestWith([GdPngEncoder::class, '2x2.webp', 'image/png'])]
    #[TestWith([GdPngEncoder::class, '2x2.avif', 'image/png'])]
    public function testEncodesFixture(string $encoderClass, string $source, string $expectedMime): void
    {
        $runtime = new RuntimeCapabilities();
        $encoder = new $encoderClass($runtime, $this->localDriver, new GdImageDecoder($runtime));
        if (!$encoder->isAvailable()) {
            self::markTestSkipped('GD cannot write this format on this runtime.');
        }
        $output = $this->outputPath($encoder->getFormatCode());

        $encoder->encode(self::FIXTURES . $source, $output, 80);

        self::assertSame(
            $expectedMime,
            (new \finfo(FILEINFO_MIME_TYPE))->buffer($this->localDriver->fileGetContents($output))
        );
    }

    /**
     * A source GD cannot decode, or cannot read, fails as an encoding failure
     *
     * @param string $source
     * @return void
     */
    #[TestWith(['not-an-image.txt'])]
    #[TestWith(['missing.png'])]
    public function testUndecodableSourceFails(string $source): void
    {
        $encoder = new GdPngEncoder(
            new RuntimeCapabilities(),
            $this->localDriver,
            new GdImageDecoder(new RuntimeCapabilities())
        );
        if (!$encoder->isAvailable()) {
            self::markTestSkipped('GD is not available on this runtime.');
        }

        $this->expectException(EncodingException::class);
        $encoder->encode(self::FIXTURES . $source, $this->outputPath('png'), 80);
    }

    /**
     * An encoder over a fake runtime
     *
     * @param class-string<AbstractGdEncoder> $encoderClass
     * @param list<string> $functions
     * @param list<string> $gdFeatures
     * @return AbstractGdEncoder
     */
    private function encoder(string $encoderClass, array $functions, array $gdFeatures): AbstractGdEncoder
    {
        $runtime = $this->createMock(RuntimeCapabilities::class);
        $runtime->method('hasFunction')
            ->willReturnCallback(static fn (string $function): bool => in_array($function, $functions, true));
        $runtime->method('gdSupports')
            ->willReturnCallback(static fn (string $feature): bool => in_array($feature, $gdFeatures, true));
        return new $encoderClass($runtime, $this->localDriver, new GdImageDecoder($runtime));
    }

    /**
     * A fresh output path, removed after the test
     *
     * @param string $extension
     * @return string
     */
    private function outputPath(string $extension): string
    {
        $output = sys_get_temp_dir() . '/hbs-gd-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $this->outputs[] = $output;

        return $output;
    }
}
