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
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaStorage::class)]
class MediaStorageTest extends TestCase
{
    /**
     * @var WriteInterface&MockObject
     */
    private MockObject $media;

    /**
     * @var LocalFileDriver&MockObject
     */
    private MockObject $localDriver;

    /**
     * @var MediaStorage
     */
    private MediaStorage $storage;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->media = $this->createMock(WriteInterface::class);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->with(DirectoryList::MEDIA)->willReturn($this->media);
        $this->localDriver = $this->createMock(LocalFileDriver::class);
        $this->storage = new MediaStorage(
            $filesystem,
            new MediaPaths(['image' => 'banner_slider/image', 'responsive' => 'banner_slider/responsive']),
            $this->localDriver
        );
    }

    /**
     * Reads accept a safe path outside the package roots
     *
     * @return void
     */
    public function testReadsAcceptLegacyFolders(): void
    {
        $this->media->method('isExist')->with('legacy_slider/image/a.jpg')->willReturn(true);
        $this->media->method('readFile')->with('legacy_slider/image/a.jpg')->willReturn('bytes');
        $this->media->method('stat')->with('legacy_slider/image/a.jpg')->willReturn(['size' => 5]);

        self::assertTrue($this->storage->exists('legacy_slider/image/a.jpg'));
        self::assertSame('bytes', $this->storage->read('legacy_slider/image/a.jpg'));
        self::assertSame(5, $this->storage->size('legacy_slider/image/a.jpg'));
    }

    /**
     * A size the directory does not report is an error
     *
     * @return void
     */
    public function testUnknownSizeFails(): void
    {
        $this->media->method('stat')->willReturn([]);

        $this->expectException(FileSystemException::class);
        $this->storage->size('banner_slider/image/a.jpg');
    }

    /**
     * The modification time comes from the directory's stat of the file
     *
     * @return void
     */
    public function testModifiedAt(): void
    {
        $this->media->method('stat')->with('banner_slider/image/a.jpg')->willReturn(['mtime' => 1700000000]);

        self::assertSame(1700000000, $this->storage->modifiedAt('banner_slider/image/a.jpg'));
    }

    /**
     * A modification time the directory does not report is an error
     *
     * @return void
     */
    public function testUnknownModificationTimeFails(): void
    {
        $this->media->method('stat')->willReturn(['size' => 3]);

        $this->expectException(FileSystemException::class);
        $this->storage->modifiedAt('banner_slider/image/a.jpg');
    }

    /**
     * Reads refuse a path that could leave the media directory, before touching it
     *
     * @return void
     */
    public function testReadRefusesTraversal(): void
    {
        $this->media->expects(self::never())->method('readFile');

        $this->expectException(\InvalidArgumentException::class);
        $this->storage->read('banner_slider/../../app/etc/env.php');
    }

    /**
     * Every mutation outside the package roots is refused before the directory is touched
     *
     * @param string $operation
     * @return void
     */
    #[TestWith(['write'])]
    #[TestWith(['copyFromLocal'])]
    #[TestWith(['move'])]
    #[TestWith(['delete'])]
    #[TestWith(['deleteDirectory'])]
    #[TestWith(['listFiles'])]
    #[TestWith(['touch'])]
    public function testMutationOutsideRootsIsRefused(string $operation): void
    {
        $this->media->expects(self::never())->method('writeFile');
        $this->media->expects(self::never())->method('delete');
        $this->media->expects(self::never())->method('renameFile');
        $this->media->expects(self::never())->method('create');
        $this->media->expects(self::never())->method('isDirectory');
        $this->media->expects(self::never())->method('touch');
        $this->localDriver->expects(self::never())->method('copy');

        $this->expectException(\InvalidArgumentException::class);
        $outside = 'legacy_slider/image/a.jpg';
        $operations = [
            'write' => function () use ($outside): void {
                $this->storage->write($outside, 'x');
            },
            'copyFromLocal' => function () use ($outside): void {
                $this->storage->copyFromLocal('/tmp/a.jpg', $outside);
            },
            'move' => function () use ($outside): void {
                $this->storage->move('banner_slider/image/a.jpg', $outside);
            },
            'delete' => function () use ($outside): void {
                $this->storage->delete($outside);
            },
            'deleteDirectory' => function (): void {
                $this->storage->deleteDirectory('legacy_slider');
            },
            'listFiles' => function (): void {
                $this->storage->listFiles('legacy_slider');
            },
            'touch' => function () use ($outside): void {
                $this->storage->touch($outside);
            },
        ];
        $operations[$operation]();
    }

    /**
     * Writes, moves and deletes inside the roots reach the directory
     *
     * @return void
     */
    public function testMutationsInsideRoots(): void
    {
        $this->media->expects(self::once())->method('writeFile')->with('banner_slider/image/a.jpg', 'x');
        $this->media->expects(self::once())->method('renameFile')
            ->with('banner_slider/image/a.jpg', 'banner_slider/responsive/1/a.jpg');
        $this->media->expects(self::exactly(2))->method('delete')
            ->willReturnCallback(static fn (string $path): bool => in_array(
                $path,
                ['banner_slider/image/a.jpg', 'banner_slider/responsive/1'],
                true
            ));

        $this->storage->write('banner_slider/image/a.jpg', 'x');
        $this->storage->move('banner_slider/image/a.jpg', 'banner_slider/responsive/1/a.jpg');
        $this->storage->delete('banner_slider/image/a.jpg');
        $this->storage->deleteDirectory('banner_slider/responsive/1');
    }

    /**
     * Touching a file inside the roots reaches the directory
     *
     * @return void
     */
    public function testTouchInsideRoots(): void
    {
        $this->media->expects(self::once())->method('touch')->with('banner_slider/image/2026/09/a-1.jpg');

        $this->storage->touch('banner_slider/image/2026/09/a-1.jpg');
    }

    /**
     * A package root itself is never deleted as a folder
     *
     * @return void
     */
    public function testRootFolderIsNotDeleted(): void
    {
        $this->media->expects(self::never())->method('delete');

        $this->expectException(\InvalidArgumentException::class);
        $this->storage->deleteDirectory('banner_slider/responsive/');
    }

    /**
     * A local file is copied through the drivers after its media folder is created
     *
     * @return void
     */
    public function testCopyFromLocal(): void
    {
        $mediaDriver = $this->createMock(DriverInterface::class);
        $this->media->method('getDriver')->willReturn($mediaDriver);
        $this->media->expects(self::once())->method('create')->with('banner_slider/image/2026/09');
        $this->media->method('getAbsolutePath')->with('banner_slider/image/2026/09/a.jpg')
            ->willReturn('/media/banner_slider/image/2026/09/a.jpg');
        $this->localDriver->expects(self::once())->method('copy')
            ->with('/tmp/upload', '/media/banner_slider/image/2026/09/a.jpg', $mediaDriver)
            ->willReturn(true);

        $this->storage->copyFromLocal('/tmp/upload', 'banner_slider/image/2026/09/a.jpg');
    }

    /**
     * Files are listed recursively, folders left out, sorted and relative to media
     *
     * @return void
     */
    public function testListFiles(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $this->media->method('getDriver')->willReturn($driver);
        $this->media->method('isDirectory')->with('banner_slider/responsive')->willReturn(true);
        $this->media->method('getAbsolutePath')->with('banner_slider/responsive')
            ->willReturn('/media/banner_slider/responsive');
        $driver->method('readDirectoryRecursively')->willReturn([
            '/media/banner_slider/responsive/2/b.png',
            '/media/banner_slider/responsive/2',
            '/media/banner_slider/responsive/1/a.png',
        ]);
        $driver->method('isFile')->willReturnCallback(static fn (string $path): bool => str_ends_with($path, '.png'));
        $this->media->method('getRelativePath')
            ->willReturnCallback(static fn (string $path): string => substr($path, strlen('/media/')));

        self::assertSame(
            ['banner_slider/responsive/1/a.png', 'banner_slider/responsive/2/b.png'],
            $this->storage->listFiles('banner_slider/responsive')
        );
    }

    /**
     * A missing folder lists nothing
     *
     * @return void
     */
    public function testListFilesOfMissingFolder(): void
    {
        $this->media->method('isDirectory')->willReturn(false);

        self::assertSame([], $this->storage->listFiles('banner_slider/image'));
    }
}
