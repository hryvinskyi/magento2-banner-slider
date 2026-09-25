<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\GdImageDecoder;
use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Hryvinskyi\BannerSlider\Model\Image\JpegOrientationNormaliser;
use Hryvinskyi\BannerSlider\Model\Image\RuntimeCapabilities;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(JpegOrientationNormaliser::class)]
class JpegOrientationNormaliserTest extends TestCase
{
    private const IMAGES = __DIR__ . '/../../_files/images/';

    /**
     * @var LocalFileDriver
     */
    private LocalFileDriver $driver;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * Temp files handed out by the workspace double
     *
     * @var list<string>
     */
    private array $tempFiles = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->driver = new LocalFileDriver();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if ($this->driver->isExists($tempFile)) {
                $this->driver->deleteFile($tempFile);
            }
        }
    }

    /**
     * A 2x3 JPEG stored with EXIF orientation 6 (rotate 90° clockwise to view) is handed over as an upright 3x2 copy
     * that is not an HTTP upload and carries the copy's size; the copy is removed afterwards
     *
     * @return void
     */
    public function testRotatedJpegIsTurnedUpright(): void
    {
        $normaliser = $this->realNormaliser();
        $file = $this->file('2x3-orientation-6.jpg');
        $inspector = new ImageInspector($this->driver);
        $seen = $normaliser->withUpright(
            $file,
            'image/jpeg',
            static fn (UploadedFile $upright): array => [
                'upright' => $upright,
                'info' => $inspector->inspect($upright->getTemporaryPath()),
            ]
        );
        $upright = $seen['upright'];
        $info = $seen['info'];

        self::assertNotSame($file, $upright);
        self::assertFalse($upright->isHttpUpload());
        self::assertSame('phone.jpg', $upright->getClientFileName());
        self::assertNotNull($info);
        self::assertSame('image/jpeg', $info['mime']);
        self::assertSame([3, 2], [$info['width'], $info['height']]);
        self::assertGreaterThan(0, $upright->getSize());
        self::assertSame([$upright->getTemporaryPath()], $this->tempFiles);
        self::assertFalse($this->driver->isExists($upright->getTemporaryPath()));
    }

    /**
     * Another type, and a JPEG without a rotating orientation, are handed over as they are
     *
     * @param string $fixture
     * @param string $mime
     * @return void
     */
    #[TestWith(['2x2.png', 'image/png'])]
    #[TestWith(['2x2.jpg', 'image/jpeg'])]
    #[TestWith(['2x3-orientation-6.jpg', 'image/png'])]
    public function testOtherFilesAreUsedAsTheyAre(string $fixture, string $mime): void
    {
        $file = $this->file($fixture);

        $result = $this->realNormaliser()->withUpright(
            $file,
            $mime,
            static fn (UploadedFile $used): UploadedFile => $used
        );

        self::assertSame($file, $result);
        self::assertSame([], $this->tempFiles);
    }

    /**
     * Without the exif extension a JPEG is used as it is, and that is logged once
     *
     * @return void
     */
    public function testMissingExifIsLoggedOnce(): void
    {
        $runtime = $this->createMock(RuntimeCapabilities::class);
        $runtime->method('hasFunction')->willReturn(false);
        $this->logger->expects(self::once())->method('warning')->with(self::stringContains('exif extension'));
        $normaliser = $this->normaliser($runtime);
        $file = $this->file('2x3-orientation-6.jpg');

        self::assertSame($file, $normaliser->withUpright($file, 'image/jpeg', static fn (UploadedFile $f) => $f));
        self::assertSame($file, $normaliser->withUpright($file, 'image/jpeg', static fn (UploadedFile $f) => $f));
    }

    /**
     * A normaliser over the real runtime, or a skipped test when it cannot read EXIF or decode JPEG
     *
     * @return JpegOrientationNormaliser
     */
    private function realNormaliser(): JpegOrientationNormaliser
    {
        $runtime = new RuntimeCapabilities();
        if (!$runtime->hasFunction('exif_read_data')
            || !(new GdImageDecoder($runtime, ['jpeg' => 'JPEG Support']))->canDecode('jpeg')
        ) {
            self::markTestSkipped('This runtime cannot read EXIF or decode JPEG.');
        }

        return $this->normaliser($runtime);
    }

    /**
     * The normaliser over a runtime and a workspace double that hands out system temp files
     *
     * @param RuntimeCapabilities $runtime
     * @return JpegOrientationNormaliser
     */
    private function normaliser(RuntimeCapabilities $runtime): JpegOrientationNormaliser
    {
        $workspace = $this->createMock(LocalFileWorkspace::class);
        $workspace->method('newTempPath')->willReturnCallback(function (string $extension): string {
            $path = sys_get_temp_dir() . '/hbs-upright-' . bin2hex(random_bytes(8)) . '.' . $extension;
            $this->tempFiles[] = $path;

            return $path;
        });
        $workspace->method('discard')->willReturnCallback(function (string $path): void {
            if ($this->driver->isExists($path)) {
                $this->driver->deleteFile($path);
            }
        });
        $imageConfig = $this->createMock(ImageConfigInterface::class);
        $imageConfig->method('getDefaultQuality')->with('jpeg')->willReturn(90);

        return new JpegOrientationNormaliser(
            $runtime,
            new GdImageDecoder($runtime, ['jpeg' => 'JPEG Support']),
            $workspace,
            $this->driver,
            $imageConfig,
            $this->logger
        );
    }

    /**
     * A received HTTP upload of an image fixture
     *
     * @param string $fixture
     * @return UploadedFile
     */
    private function file(string $fixture): UploadedFile
    {
        return new UploadedFile(self::IMAGES . $fixture, 'phone.jpg', 100, UPLOAD_ERR_OK, true);
    }
}
