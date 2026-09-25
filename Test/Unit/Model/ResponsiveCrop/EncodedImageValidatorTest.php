<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\GdImageDecoder;
use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Hryvinskyi\BannerSlider\Model\Image\RuntimeCapabilities;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\EncodedImageValidator;
use Hryvinskyi\BannerSlider\Test\Unit\Model\ImageFormats;
use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\EncodedImage;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncodedImageValidator::class)]
class EncodedImageValidatorTest extends TestCase
{
    use ImageFormats;

    private const FIXTURES = __DIR__ . '/../../_files/images/';
    private const TEMP_FILE = '/srv/var/tmp/hryvinskyi_banner_slider/check.bin';
    private const GD_FORMATS = [
        'jpeg' => 'JPEG Support',
        'png' => 'PNG Support',
        'webp' => 'WebP Support',
        'avif' => 'AVIF Support',
    ];

    /**
     * @var ImageConfigInterface&MockObject
     */
    private MockObject $imageConfig;

    /**
     * @var ImageInspector&MockObject
     */
    private MockObject $inspector;

    /**
     * Temp files written, by path
     *
     * @var array<string,string>
     */
    private array $written = [];

    /**
     * Temp files discarded
     *
     * @var list<string>
     */
    private array $discarded = [];

    /**
     * @var EncodedImageValidator
     */
    private EncodedImageValidator $validator;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->imageConfig = $this->createMock(ImageConfigInterface::class);
        $this->imageConfig->method('getMaxUploadBytes')->willReturn(100000);
        $this->inspector = $this->createMock(ImageInspector::class);
        $workspace = $this->createMock(LocalFileWorkspace::class);
        $workspace->method('newTempPath')->willReturn(self::TEMP_FILE);
        $workspace->method('discard')->willReturnCallback(function (string $path): void {
            $this->discarded[] = $path;
        });
        $localDriver = $this->createMock(LocalFileDriver::class);
        $localDriver->method('filePutContents')->willReturnCallback(function (string $path, string $bytes): int {
            $this->written[$path] = $bytes;

            return strlen($bytes);
        });

        $this->validator = new EncodedImageValidator(
            $this->imageConfig,
            $this->formatRegistry(),
            $this->inspector,
            $workspace,
            $localDriver,
            new GdImageDecoder(new RuntimeCapabilities(), self::GD_FORMATS)
        );
    }

    /**
     * Real image bytes of the declared type and the target size pass, and the temp copy is removed
     *
     * @param string $file
     * @param string $code
     * @param string $mime
     * @return void
     */
    #[TestWith(['2x2.png', 'png', 'image/png'])]
    #[TestWith(['2x2.jpg', 'jpeg', 'image/jpeg'])]
    #[TestWith(['2x2.webp', 'webp', 'image/webp'])]
    #[TestWith(['2x2.avif', 'avif', 'image/avif'])]
    public function testValidImage(string $file, string $code, string $mime): void
    {
        $bytes = $this->fixture($file);
        $this->inspector->method('inspect')->with(self::TEMP_FILE)
            ->willReturn(['mime' => $mime, 'width' => 2, 'height' => 2]);

        $format = $this->validator->validate(
            new EncodedImage($code, $bytes),
            $this->breakpoint(),
            new Dimensions(2, 2)
        );

        self::assertSame($code, $format->getCode());
        self::assertSame([self::TEMP_FILE => $bytes], $this->written);
        self::assertSame([self::TEMP_FILE], $this->discarded);
    }

    /**
     * A size off by one pixel, from rounding in the browser, is accepted
     *
     * @return void
     */
    public function testOnePixelTolerance(): void
    {
        $this->inspector->method('inspect')->willReturn(['mime' => 'image/png', 'width' => 3, 'height' => 1]);

        self::assertSame('png', $this->validator->validate(
            new EncodedImage('png', $this->fixture('2x2.png')),
            $this->breakpoint(),
            new Dimensions(2, 2)
        )->getCode());
    }

    /**
     * Bytes over the upload cap are refused before anything else is looked at
     *
     * @return void
     */
    public function testTooLarge(): void
    {
        $this->imageConfig = $this->createMock(ImageConfigInterface::class);
        $this->imageConfig->method('getMaxUploadBytes')->willReturn(10);
        $validator = new EncodedImageValidator(
            $this->imageConfig,
            $this->formatRegistry(),
            $this->inspector,
            $this->createMock(LocalFileWorkspace::class),
            $this->createMock(LocalFileDriver::class),
            new GdImageDecoder(new RuntimeCapabilities(), self::GD_FORMATS)
        );
        $this->inspector->expects(self::never())->method('inspect');

        $this->expectExceptionObject(new LocalizedException(
            __('The %1 image supplied for breakpoint "%2" is larger than %3 bytes.', 'png', 'desktop', 10)
        ));
        $validator->validate(
            new EncodedImage('png', $this->fixture('2x2.png')),
            $this->breakpoint(),
            new Dimensions(2, 2)
        );
    }

    /**
     * A format the registry does not know is refused
     *
     * @return void
     */
    public function testUnknownFormat(): void
    {
        $this->expectExceptionMessage('declares the unknown format "heic"');

        $this->validator->validate(new EncodedImage('heic', 'bytes'), $this->breakpoint(), new Dimensions(2, 2));
    }

    /**
     * Bytes of another type than declared are refused, whatever they are
     *
     * @param string $file
     * @param string $declared
     * @return void
     */
    #[TestWith(['2x2.png', 'webp'])]
    #[TestWith(['2x2.jpg', 'png'])]
    #[TestWith(['not-an-image.txt', 'png'])]
    #[TestWith(['2x2.svg', 'png'])]
    public function testTypeMismatch(string $file, string $declared): void
    {
        $this->inspector->expects(self::never())->method('inspect');

        $this->expectExceptionMessage('supplied for breakpoint "desktop" as ' . $declared . ' is really of type');
        $this->validator->validate(
            new EncodedImage($declared, $this->fixture($file)),
            $this->breakpoint(),
            new Dimensions(2, 2)
        );
    }

    /**
     * Bytes with the right signature that do not decode are refused, and the temp copy is still removed
     *
     * @return void
     */
    public function testUndecodable(): void
    {
        $this->inspector->method('inspect')->willReturn(null);

        try {
            $this->validator->validate(
                new EncodedImage('png', $this->fixture('2x2.png')),
                $this->breakpoint(),
                new Dimensions(2, 2)
            );
            self::fail('Undecodable bytes must be refused.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('cannot be decoded', $exception->getMessage());
        }
        self::assertSame([self::TEMP_FILE], $this->discarded);
    }

    /**
     * A PNG cut off after its header reads as a 2x2 image but does not decode, so it is refused
     *
     * @return void
     */
    public function testTruncatedBodyIsRefused(): void
    {
        if (!(new GdImageDecoder(new RuntimeCapabilities(), self::GD_FORMATS))->canDecode('png')) {
            self::markTestSkipped('GD cannot decode PNG on this runtime.');
        }
        $this->inspector->method('inspect')->willReturn(['mime' => 'image/png', 'width' => 2, 'height' => 2]);

        try {
            $this->validator->validate(
                new EncodedImage('png', substr($this->fixture('2x2.png'), 0, 40)),
                $this->breakpoint(),
                new Dimensions(2, 2)
            );
            self::fail('A truncated body must be refused.');
        } catch (LocalizedException $exception) {
            self::assertSame(
                'The png image supplied for breakpoint "desktop" cannot be decoded.',
                $exception->getMessage()
            );
        }
        self::assertSame([self::TEMP_FILE], $this->discarded);
    }

    /**
     * A format this server's GD cannot read is checked by its header only
     *
     * @return void
     */
    public function testFormatGdCannotReadIsCheckedByHeader(): void
    {
        $workspace = $this->createMock(LocalFileWorkspace::class);
        $workspace->method('newTempPath')->willReturn(self::TEMP_FILE);
        $this->inspector->method('inspect')->willReturn(['mime' => 'image/png', 'width' => 2, 'height' => 2]);
        $validator = new EncodedImageValidator(
            $this->imageConfig,
            $this->formatRegistry(),
            $this->inspector,
            $workspace,
            $this->createMock(LocalFileDriver::class),
            new GdImageDecoder(new RuntimeCapabilities(), [])
        );

        self::assertSame('png', $validator->validate(
            new EncodedImage('png', substr($this->fixture('2x2.png'), 0, 40)),
            $this->breakpoint(),
            new Dimensions(2, 2)
        )->getCode());
    }

    /**
     * Bytes of another size than the crop's target are refused
     *
     * @return void
     */
    public function testWrongSize(): void
    {
        $this->inspector->method('inspect')->willReturn(['mime' => 'image/png', 'width' => 2, 'height' => 2]);

        $this->expectExceptionMessage(
            'The png image supplied for breakpoint "desktop" is 2x2 pixels; the breakpoint needs 1920x600.'
        );
        $this->validator->validate(
            new EncodedImage('png', $this->fixture('2x2.png')),
            $this->breakpoint(),
            new Dimensions(1920, 600)
        );
    }

    /**
     * The bytes of a committed fixture
     *
     * @param string $file
     * @return string
     */
    private function fixture(string $file): string
    {
        return (new LocalFileDriver())->fileGetContents(self::FIXTURES . $file);
    }

    /**
     * A breakpoint named "desktop"
     *
     * @return BreakpointInterface
     */
    private function breakpoint(): BreakpointInterface
    {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('getIdentifier')->willReturn('desktop');

        return $breakpoint;
    }
}
