<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\MediaStorage;
use Hryvinskyi\BannerSlider\Model\Media\UploadedFileStore;
use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(UploadedFileStore::class)]
class UploadedFileStoreTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../_files/images/2x2.png';

    /**
     * @var MediaStorage&MockObject
     */
    private MockObject $mediaStorage;

    /**
     * @var UploadedFileStore
     */
    private UploadedFileStore $store;

    /**
     * @var string
     */
    private string $hash12;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->mediaStorage = $this->createMock(MediaStorage::class);
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-03-07 10:00:00', new \DateTimeZone('UTC')));
        $this->store = new UploadedFileStore($this->mediaStorage, $clock);
        $this->hash12 = substr((string)hash_file('sha256', self::FIXTURE), 0, 12);
    }

    /**
     * A new file is copied to root/year/month/slug-hash.extension
     *
     * @return void
     */
    public function testCopiesNewFileUnderContentName(): void
    {
        $expected = 'banner_slider/image/2026/03/holiday-photo-' . $this->hash12 . '.png';
        $this->mediaStorage->method('exists')->with($expected)->willReturn(false);
        $this->mediaStorage->expects(self::once())->method('copyFromLocal')->with(self::FIXTURE, $expected);
        $this->mediaStorage->expects(self::never())->method('touch');

        self::assertSame(
            $expected,
            $this->store->store($this->file('Holiday Photo.PNG'), '/banner_slider/image/', 'png')
        );
    }

    /**
     * The same bytes under the same name land on the same path, and an existing file is not written again but touched,
     * so the orphan sweep's grace period starts over
     *
     * @return void
     */
    public function testIdenticalUploadReusesExistingFile(): void
    {
        $this->mediaStorage->method('exists')->willReturn(true);
        $this->mediaStorage->expects(self::never())->method('copyFromLocal');
        $this->mediaStorage->expects(self::exactly(2))->method('touch')
            ->with('banner_slider/image/2026/03/banner-' . $this->hash12 . '.png');

        $first = $this->store->store($this->file('banner.png'), 'banner_slider/image', 'png');
        $second = $this->store->store($this->file('banner.png'), 'banner_slider/image', 'png');

        self::assertSame('banner_slider/image/2026/03/banner-' . $this->hash12 . '.png', $first);
        self::assertSame($first, $second);
    }

    /**
     * The client name only becomes a readable slug; its folders, extension and odd characters are dropped
     *
     * @param string $clientName
     * @param string $slug
     * @return void
     */
    #[TestWith(['Holiday Photo.PNG', 'holiday-photo'])]
    #[TestWith(['C:\\fakepath\\My   Pic!!.jpeg', 'my-pic'])]
    #[TestWith(['../../etc/passwd', 'passwd'])]
    #[TestWith(['.htaccess', 'htaccess'])]
    #[TestWith(['archive.tar.gz', 'archive-tar'])]
    #[TestWith(['Фото.jpg', 'file'])]
    #[TestWith(['', 'file'])]
    #[TestWith(['---.png', 'file'])]
    public function testSlug(string $clientName, string $slug): void
    {
        $this->mediaStorage->method('exists')->willReturn(true);

        self::assertSame(
            'banner_slider/video/2026/03/' . $slug . '-' . $this->hash12 . '.mp4',
            $this->store->store($this->file($clientName), 'banner_slider/video', 'mp4')
        );
    }

    /**
     * A long client name is cut to 64 characters
     *
     * @return void
     */
    public function testSlugIsCutTo64Characters(): void
    {
        $this->mediaStorage->method('exists')->willReturn(true);

        self::assertSame(
            'banner_slider/image/2026/03/' . str_repeat('a', 64) . '-' . $this->hash12 . '.png',
            $this->store->store($this->file(str_repeat('a', 100) . '.png'), 'banner_slider/image', 'png')
        );
    }

    /**
     * A storage failure becomes a save error
     *
     * @return void
     */
    public function testStorageFailureCannotSave(): void
    {
        $this->mediaStorage->method('exists')->willReturn(false);
        $this->mediaStorage->method('copyFromLocal')->willThrowException(new FileSystemException(__('disk full')));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('The uploaded file could not be stored.');

        $this->store->store($this->file('a.png'), 'banner_slider/image', 'png');
    }

    /**
     * An extension that is not lowercase letters or digits is a programming error
     *
     * @param string $extension
     * @return void
     */
    #[TestWith(['PNG'])]
    #[TestWith(['.png'])]
    #[TestWith(['p/g'])]
    #[TestWith([''])]
    public function testRejectsBadExtension(string $extension): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->store->store($this->file('a.png'), 'banner_slider/image', $extension);
    }

    /**
     * A received upload of the fixture under a client name
     *
     * @param string $clientName
     * @return UploadedFile
     */
    private function file(string $clientName): UploadedFile
    {
        return new UploadedFile(self::FIXTURE, $clientName, 103, UPLOAD_ERR_OK, true);
    }
}
