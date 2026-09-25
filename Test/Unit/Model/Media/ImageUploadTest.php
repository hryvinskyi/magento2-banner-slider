<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Image\GdImageDecoder;
use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Hryvinskyi\BannerSlider\Model\Image\ImagePixelLimit;
use Hryvinskyi\BannerSlider\Model\Image\JpegOrientationNormaliser;
use Hryvinskyi\BannerSlider\Model\Image\RuntimeCapabilities;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSlider\Model\Media\ImageUpload;
use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\Media\MediaStorage;
use Hryvinskyi\BannerSlider\Model\Media\UploadedFileChecker;
use Hryvinskyi\BannerSlider\Model\Media\UploadedFileStore;
use Hryvinskyi\BannerSlider\Model\Media\UploadedFileValidator;
use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(ImageUpload::class)]
class ImageUploadTest extends TestCase
{
    private const IMAGES = __DIR__ . '/../../_files/images/';
    private const FORMATS = [
        'jpeg' => ['image/jpeg', 'jpg'],
        'png' => ['image/png', 'png'],
        'gif' => ['image/gif', 'gif'],
        'webp' => ['image/webp', 'webp'],
        'avif' => ['image/avif', 'avif'],
    ];

    /**
     * @var MediaStorage&MockObject
     */
    private MockObject $mediaStorage;

    /**
     * @var ImageConfigInterface&MockObject
     */
    private MockObject $imageConfig;

    /**
     * Temp files handed out by the workspace double
     *
     * @var list<string>
     */
    private array $tempFiles = [];

    /**
     * The local path, media path and content hash of the last file copied into media
     *
     * @var array{0: string, 1: string, 2: string}
     */
    private array $copied = ['', '', ''];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->mediaStorage = $this->createMock(MediaStorage::class);
        $this->imageConfig = $this->createMock(ImageConfigInterface::class);
        $this->imageConfig->method('getMaxUploadBytes')->willReturn(1048576);
        $this->imageConfig->method('getDefaultQuality')->willReturn(90);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $driver = new LocalFileDriver();
        foreach ($this->tempFiles as $tempFile) {
            if ($driver->isExists($tempFile)) {
                $driver->deleteFile($tempFile);
            }
        }
    }

    /**
     * Each accepted image is stored under the image root with the extension of its sniffed type and its pixel size
     *
     * @param string $fixture
     * @param string $mime
     * @param string $extension
     * @return void
     */
    #[TestWith(['2x2.jpg', 'image/jpeg', 'jpg'])]
    #[TestWith(['2x2.png', 'image/png', 'png'])]
    #[TestWith(['2x2.gif', 'image/gif', 'gif'])]
    #[TestWith(['2x2.webp', 'image/webp', 'webp'])]
    #[TestWith(['2x2.avif', 'image/avif', 'avif'])]
    public function testStoresImage(string $fixture, string $mime, string $extension): void
    {
        $hash = substr((string)hash_file('sha256', self::IMAGES . $fixture), 0, 12);
        $expected = 'banner_slider/image/2026/09/summer-sale-' . $hash . '.' . $extension;
        $this->mediaStorage->method('exists')->willReturn(false);
        $this->mediaStorage->expects(self::once())->method('copyFromLocal')->with(self::IMAGES . $fixture, $expected);

        $stored = $this->upload()->upload($this->file($fixture, 'Summer Sale.bin'));

        self::assertSame($expected, $stored->getRelativePath());
        self::assertSame($mime, $stored->getMimeType());
        $dimensions = $stored->getDimensions();
        self::assertNotNull($dimensions);
        self::assertSame(2, $dimensions->getWidth());
        self::assertSame(2, $dimensions->getHeight());
    }

    /**
     * Uploading the same bytes again gives the same name and writes nothing new
     *
     * @return void
     */
    public function testIdenticalUploadGivesSameName(): void
    {
        $this->mediaStorage->method('exists')->willReturnOnConsecutiveCalls(false, true);
        $this->mediaStorage->expects(self::once())->method('copyFromLocal');
        $upload = $this->upload();

        $first = $upload->upload($this->file('2x2.png', 'banner.png'));
        $second = $upload->upload($this->file('2x2.png', 'banner.png'));

        self::assertSame($first->getRelativePath(), $second->getRelativePath());
    }

    /**
     * The size cap comes from the image settings
     *
     * @return void
     */
    public function testUsesConfiguredCap(): void
    {
        $imageConfig = $this->createMock(ImageConfigInterface::class);
        $imageConfig->method('getMaxUploadBytes')->willReturn(10);
        $this->mediaStorage->expects(self::never())->method('copyFromLocal');

        $this->expectException(ValidationException::class);

        $this->upload(imageConfig: $imageConfig)->upload($this->file('2x2.png', 'a.png'));
    }

    /**
     * A listed code the registry does not know is ignored instead of breaking uploads
     *
     * @return void
     */
    public function testUnknownListedCodeIsIgnored(): void
    {
        $this->mediaStorage->method('exists')->willReturn(true);

        $stored = $this->upload(allowedFormats: ['png', 'bmp'])->upload($this->file('2x2.png', 'a.png'));

        self::assertSame('image/png', $stored->getMimeType());
    }

    /**
     * A registered format left out of the list is refused
     *
     * @return void
     */
    public function testFormatNotListedIsRefused(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The file type is not accepted. Accepted types: jpg, png.');

        $this->upload(allowedFormats: ['jpeg', 'png'])->upload($this->file('2x2.gif', 'a.gif'));
    }

    /**
     * A file of an accepted type that does not decode as an image is refused and not stored
     *
     * @return void
     */
    public function testUndecodableImageIsRefused(): void
    {
        $inspector = $this->createMock(ImageInspector::class);
        $inspector->method('inspect')->willReturn(null);
        $this->mediaStorage->expects(self::never())->method('copyFromLocal');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The file is not a readable image.');

        $this->upload(inspector: $inspector)->upload($this->file('2x2.png', 'a.png'));
    }

    /**
     * An image header that disagrees with the sniffed type is refused
     *
     * @return void
     */
    public function testHeaderTypeMismatchIsRefused(): void
    {
        $inspector = $this->createMock(ImageInspector::class);
        $inspector->method('inspect')->willReturn(['mime' => 'image/gif', 'width' => 2, 'height' => 2]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The file is not a readable image.');

        $this->upload(inspector: $inspector)->upload($this->file('2x2.png', 'a.png'));
    }

    /**
     * An image with more pixels than the limit is refused and not stored
     *
     * @return void
     */
    public function testImageOverPixelLimitIsRefused(): void
    {
        $this->mediaStorage->expects(self::never())->method('copyFromLocal');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The image is 2x2 pixels; images may have at most 3 pixels.');

        $this->upload(maxPixels: 3)->upload($this->file('2x2.png', 'a.png'));
    }

    /**
     * A JPEG stored rotated (EXIF orientation 6) is stored upright: the stored file is a turned copy, named by its
     * own bytes, and the returned size is the upright one; the copy is removed afterwards
     *
     * @return void
     */
    public function testRotatedJpegIsStoredUpright(): void
    {
        if (!(new RuntimeCapabilities())->hasFunction('exif_read_data')
            || !(new GdImageDecoder(new RuntimeCapabilities(), ['jpeg' => 'JPEG Support']))->canDecode('jpeg')
        ) {
            self::markTestSkipped('This runtime cannot read EXIF or decode JPEG.');
        }
        $this->mediaStorage->method('exists')->willReturn(false);
        $this->mediaStorage->expects(self::once())->method('copyFromLocal')->willReturnCallback(
            function (string $localPath, string $path): void {
                $this->copied = [$localPath, $path, (string)hash_file('sha256', $localPath)];
            }
        );

        $stored = $this->upload()->upload($this->file('2x3-orientation-6.jpg', 'phone.jpg'));

        [$local, $path, $hash] = $this->copied;
        self::assertSame($this->tempFiles[0] ?? null, $local);
        self::assertSame('banner_slider/image/2026/09/phone-' . substr($hash, 0, 12) . '.jpg', $path);
        self::assertSame($path, $stored->getRelativePath());
        $dimensions = $stored->getDimensions();
        self::assertNotNull($dimensions);
        self::assertSame([3, 2], [$dimensions->getWidth(), $dimensions->getHeight()]);
        self::assertFalse((new LocalFileDriver())->isExists($local));
    }

    /**
     * The service under test with real validation and naming around mocked storage
     *
     * @param list<string> $allowedFormats
     * @param ImageConfigInterface|null $imageConfig
     * @param ImageInspector|null $inspector
     * @param int $maxPixels
     * @return ImageUpload
     */
    private function upload(
        array $allowedFormats = ['jpeg', 'png', 'gif', 'webp', 'avif'],
        ?ImageConfigInterface $imageConfig = null,
        ?ImageInspector $inspector = null,
        int $maxPixels = 1000000
    ): ImageUpload {
        $checker = $this->createMock(UploadedFileChecker::class);
        $checker->method('isUploadedFile')->willReturn(true);
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC')));

        return new ImageUpload(
            new UploadedFileValidator($checker, new LocalFileDriver()),
            $this->registry(),
            $imageConfig ?? $this->imageConfig,
            $inspector ?? new ImageInspector(new LocalFileDriver()),
            new UploadedFileStore($this->mediaStorage, $clock),
            new MediaPaths(['image' => 'banner_slider/image', 'video' => 'banner_slider/video']),
            new ImagePixelLimit($maxPixels),
            $this->normaliser(),
            $allowedFormats
        );
    }

    /**
     * A real orientation normaliser over a workspace double that hands out system temp files
     *
     * @return JpegOrientationNormaliser
     */
    private function normaliser(): JpegOrientationNormaliser
    {
        $driver = new LocalFileDriver();
        $workspace = $this->createMock(LocalFileWorkspace::class);
        $workspace->method('newTempPath')->willReturnCallback(function (string $extension): string {
            $path = sys_get_temp_dir() . '/hbs-upload-' . bin2hex(random_bytes(8)) . '.' . $extension;
            $this->tempFiles[] = $path;

            return $path;
        });
        $workspace->method('discard')->willReturnCallback(static function (string $path) use ($driver): void {
            if ($driver->isExists($path)) {
                $driver->deleteFile($path);
            }
        });
        $runtime = new RuntimeCapabilities();

        return new JpegOrientationNormaliser(
            $runtime,
            new GdImageDecoder($runtime, ['jpeg' => 'JPEG Support']),
            $workspace,
            $driver,
            $this->imageConfig,
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * A registry holding the five image formats
     *
     * @return ImageFormatRegistryInterface
     */
    private function registry(): ImageFormatRegistryInterface
    {
        $registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('has')->willReturnCallback(static fn (string $code): bool => isset(self::FORMATS[$code]));
        $registry->method('get')->willReturnCallback(
            static fn (string $code): ImageFormat => new ImageFormat($code, ...self::FORMATS[$code])
        );

        return $registry;
    }

    /**
     * A received HTTP upload of an image fixture
     *
     * @param string $fixture
     * @param string $clientName
     * @return UploadedFile
     */
    private function file(string $fixture, string $clientName): UploadedFile
    {
        return new UploadedFile(self::IMAGES . $fixture, $clientName, 100, UPLOAD_ERR_OK, true);
    }
}
