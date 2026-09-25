<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\Media\MediaStorage;
use Hryvinskyi\BannerSlider\Model\Media\UploadedFileChecker;
use Hryvinskyi\BannerSlider\Model\Media\UploadedFileStore;
use Hryvinskyi\BannerSlider\Model\Media\UploadedFileValidator;
use Hryvinskyi\BannerSlider\Model\Media\VideoUpload;
use Hryvinskyi\BannerSliderApi\Api\Config\VideoConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(VideoUpload::class)]
class VideoUploadTest extends TestCase
{
    private const FILES = __DIR__ . '/../../_files/';
    private const TYPES = [
        'mp4' => ['mime' => 'video/mp4', 'extension' => 'mp4'],
        'm4v' => ['mime' => 'video/x-m4v', 'extension' => 'mp4'],
        'webm' => ['mime' => 'video/webm', 'extension' => 'webm'],
    ];

    /**
     * @var MediaStorage&MockObject
     */
    private MockObject $mediaStorage;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->mediaStorage = $this->createMock(MediaStorage::class);
    }

    /**
     * A video is stored under the video root with the extension of its sniffed type and no pixel size
     *
     * @param string $fixture
     * @param string $mime
     * @param string $extension
     * @return void
     */
    #[TestWith(['video/tiny.mp4', 'video/mp4', 'mp4'])]
    #[TestWith(['video/tiny.webm', 'video/webm', 'webm'])]
    #[TestWith(['video/tiny.m4v', 'video/x-m4v', 'mp4'])]
    public function testStoresVideo(string $fixture, string $mime, string $extension): void
    {
        $hash = substr((string)hash_file('sha256', self::FILES . $fixture), 0, 12);
        $expected = 'banner_slider/video/2026/09/promo-' . $hash . '.' . $extension;
        $this->mediaStorage->method('exists')->willReturn(false);
        $this->mediaStorage->expects(self::once())->method('copyFromLocal')->with(self::FILES . $fixture, $expected);

        $stored = $this->upload()->upload($this->file($fixture, 'Promo.mov'));

        self::assertSame($expected, $stored->getRelativePath());
        self::assertSame($mime, $stored->getMimeType());
        self::assertNull($stored->getDimensions());
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

        $first = $upload->upload($this->file('video/tiny.mp4', 'promo.mp4'));
        $second = $upload->upload($this->file('video/tiny.mp4', 'promo.mp4'));

        self::assertSame($first->getRelativePath(), $second->getRelativePath());
    }

    /**
     * A type no local video provider plays is refused, including an image renamed to a video
     *
     * @return void
     */
    public function testRefusesOtherTypes(): void
    {
        $this->mediaStorage->expects(self::never())->method('copyFromLocal');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Accepted types: mp4, webm.');

        $this->upload()->upload($this->file('images/2x2.png', 'clip.mp4'));
    }

    /**
     * The size cap comes from the video settings
     *
     * @return void
     */
    public function testUsesConfiguredCap(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('larger than');

        $this->upload(maxBytes: 8)->upload($this->file('video/tiny.mp4', 'a.mp4'));
    }

    /**
     * An accepted-types list with an empty or repeated entry is a configuration error
     *
     * @param array<string,array<string,string>> $types
     * @return void
     */
    #[TestWith([['mp4' => ['mime' => 'video/mp4', 'extension' => '']]])]
    #[TestWith([['mp4' => ['mime' => '', 'extension' => 'mp4']]])]
    #[TestWith([['mp4' => ['extension' => 'mp4']]])]
    #[TestWith([['mp4' => ['mime' => 'video/mp4']]])]
    #[TestWith([[
        'mp4' => ['mime' => 'video/mp4', 'extension' => 'mp4'],
        'other' => ['mime' => 'VIDEO/MP4', 'extension' => 'm4v'],
    ]])]
    public function testRejectsBrokenTypeList(array $types): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->upload(types: $types);
    }

    /**
     * The service under test with real validation and naming around mocked storage
     *
     * @param array<string,array<string,string>> $types
     * @param int $maxBytes
     * @return VideoUpload
     */
    private function upload(array $types = self::TYPES, int $maxBytes = 1048576): VideoUpload
    {
        $checker = $this->createMock(UploadedFileChecker::class);
        $checker->method('isUploadedFile')->willReturn(true);
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC')));
        $videoConfig = $this->createMock(VideoConfigInterface::class);
        $videoConfig->method('getMaxUploadBytes')->willReturn($maxBytes);

        return new VideoUpload(
            new UploadedFileValidator($checker, new LocalFileDriver()),
            $videoConfig,
            new UploadedFileStore($this->mediaStorage, $clock),
            new MediaPaths(['image' => 'banner_slider/image', 'video' => 'banner_slider/video']),
            $types
        );
    }

    /**
     * A received HTTP upload of a fixture
     *
     * @param string $fixture Path below the fixture folder
     * @param string $clientName
     * @return UploadedFile
     */
    private function file(string $fixture, string $clientName): UploadedFile
    {
        return new UploadedFile(self::FILES . $fixture, $clientName, 32, UPLOAD_ERR_OK, true);
    }
}
