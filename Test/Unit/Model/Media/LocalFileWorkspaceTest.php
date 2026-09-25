<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalFileWorkspace::class)]
class LocalFileWorkspaceTest extends TestCase
{
    private const VAR_ROOT = '/srv/var/';
    private const TEMP_FOLDER = 'tmp/hryvinskyi_banner_slider';

    /**
     * @var WriteInterface&MockObject
     */
    private MockObject $media;

    /**
     * @var WriteInterface&MockObject
     */
    private MockObject $var;

    /**
     * @var LocalFileWorkspace
     */
    private LocalFileWorkspace $workspace;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->media = $this->createMock(WriteInterface::class);
        $this->var = $this->createMock(WriteInterface::class);
        $this->var->method('getAbsolutePath')
            ->willReturnCallback(static fn (?string $path = null): string => self::VAR_ROOT . ($path ?? ''));
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturnCallback(
            fn (string $code): WriteInterface => $code === DirectoryList::MEDIA ? $this->media : $this->var
        );
        $this->workspace = new LocalFileWorkspace(
            $filesystem,
            new MediaPaths(['image' => 'banner_slider/image']),
            self::TEMP_FOLDER
        );
    }

    /**
     * On local-disk media the media file's own path is handed out and nothing is copied
     *
     * @return void
     */
    public function testLocalDiskMediaIsUsedInPlace(): void
    {
        $this->media->method('isFile')->willReturn(true);
        $this->media->method('getDriver')->willReturn($this->createMock(LocalFileDriver::class));
        $this->media->method('getAbsolutePath')->with('legacy_slider/image/a.jpg')
            ->willReturn('/srv/pub/media/legacy_slider/image/a.jpg');
        $this->var->expects(self::never())->method('writeFile');
        $this->var->expects(self::never())->method('delete');

        $result = $this->workspace->withLocalCopy(
            'legacy_slider/image/a.jpg',
            static fn (string $local): string => 'seen ' . $local
        );

        self::assertSame('seen /srv/pub/media/legacy_slider/image/a.jpg', $result);
    }

    /**
     * On remote media a temp copy is made, handed out and removed afterwards
     *
     * @return void
     */
    public function testRemoteMediaIsCopiedAndCleanedUp(): void
    {
        $this->givenRemoteMediaFile('banner_slider/image/a.JPG', 'bytes');
        $written = null;
        $this->var->expects(self::once())->method('writeFile')
            ->willReturnCallback(function (string $path, string $bytes) use (&$written): int {
                $written = $path;
                self::assertSame('bytes', $bytes);

                return 5;
            });
        $this->var->expects(self::once())->method('delete')
            ->willReturnCallback(function (string $path) use (&$written): bool {
                self::assertSame($written, $path);

                return true;
            });

        $local = $this->workspace->withLocalCopy(
            'banner_slider/image/a.JPG',
            static fn (string $path): string => $path
        );

        self::assertStringStartsWith(self::VAR_ROOT . self::TEMP_FOLDER . '/', $local);
        self::assertStringEndsWith('.jpg', $local);
        self::assertSame(self::VAR_ROOT . $written, $local);
    }

    /**
     * The temp copy is removed when the callback throws, and the exception reaches the caller
     *
     * @return void
     */
    public function testTempCopyIsRemovedWhenCallbackThrows(): void
    {
        $this->givenRemoteMediaFile('banner_slider/image/a.png', 'bytes');
        $this->var->method('writeFile')->willReturn(5);
        $this->var->expects(self::once())->method('delete')
            ->with(self::stringStartsWith(self::TEMP_FOLDER . '/'))
            ->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('library failed');
        $this->workspace->withLocalCopy('banner_slider/image/a.png', static function (): never {
            throw new \RuntimeException('library failed');
        });
    }

    /**
     * A missing media file fails with a message naming the relative path only
     *
     * @return void
     */
    public function testMissingMediaFileFails(): void
    {
        $this->media->method('isFile')->willReturn(false);

        $this->expectException(FileSystemException::class);
        $this->expectExceptionMessage('The media file "banner_slider/image/missing.png" does not exist.');
        $this->workspace->withLocalCopy('banner_slider/image/missing.png', static fn (string $path): string => $path);
    }

    /**
     * An unsafe media path is refused before the directory is touched
     *
     * @return void
     */
    public function testUnsafeMediaPathIsRefused(): void
    {
        $this->media->expects(self::never())->method('isFile');

        $this->expectException(\InvalidArgumentException::class);
        $this->workspace->withLocalCopy('../app/etc/env.php', static fn (string $path): string => $path);
    }

    /**
     * New temp paths are unique names in the temp folder with the requested extension
     *
     * @return void
     */
    public function testNewTempPath(): void
    {
        $this->var->expects(self::exactly(2))->method('create')->with(self::TEMP_FOLDER);

        $first = $this->workspace->newTempPath('webp');
        $second = $this->workspace->newTempPath('webp');

        self::assertMatchesRegularExpression('#^/srv/var/tmp/hryvinskyi_banner_slider/[0-9a-f]{32}\.webp$#', $first);
        self::assertNotSame($first, $second);
    }

    /**
     * An extension that is not plain lowercase letters or digits is refused
     *
     * @return void
     */
    public function testNewTempPathRefusesBadExtension(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->workspace->newTempPath('../php');
    }

    /**
     * Only files of the temp folder can be discarded
     *
     * @return void
     */
    public function testDiscard(): void
    {
        $this->var->expects(self::once())->method('delete')->with(self::TEMP_FOLDER . '/abc.png')->willReturn(true);
        $this->workspace->discard(self::VAR_ROOT . self::TEMP_FOLDER . '/abc.png');

        $this->expectException(\InvalidArgumentException::class);
        $this->workspace->discard(self::VAR_ROOT . self::TEMP_FOLDER . '/../../app/etc/env.php');
    }

    /**
     * A media file on a remote driver
     *
     * @param string $path
     * @param string $bytes
     * @return void
     */
    private function givenRemoteMediaFile(string $path, string $bytes): void
    {
        $this->media->method('isFile')->with($path)->willReturn(true);
        $this->media->method('getDriver')->willReturn($this->createMock(DriverInterface::class));
        $this->media->method('readFile')->with($path)->willReturn($bytes);
    }
}
