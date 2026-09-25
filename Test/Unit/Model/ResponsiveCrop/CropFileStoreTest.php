<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\Media\MediaStorage;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileNamer;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileStore;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CropFileStore::class)]
class CropFileStoreTest extends TestCase
{
    /**
     * @var MediaStorage&MockObject
     */
    private MockObject $mediaStorage;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var CropFileStore
     */
    private CropFileStore $store;

    /**
     * @var string
     */
    private string $expectedPath;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->mediaStorage = $this->createMock(MediaStorage::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->store = new CropFileStore(
            new CropFileNamer(new MediaPaths(['responsive' => 'banner_slider/responsive'])),
            $this->mediaStorage,
            $this->logger
        );
        $this->expectedPath = 'banner_slider/responsive/5/desktop_' . substr(hash('sha256', 'bytes'), 0, 12) . '.png';
    }

    /**
     * A new name is written and reported as created
     *
     * @return void
     */
    public function testWritesNewFile(): void
    {
        $this->mediaStorage->method('exists')->willReturn(false);
        $this->mediaStorage->expects(self::once())->method('write')->with($this->expectedPath, 'bytes');

        $file = $this->store->store(5, 'desktop', $this->png(), 'bytes');

        self::assertSame($this->expectedPath, $file->getPath());
        self::assertTrue($file->isCreated());
    }

    /**
     * An identical existing file is reused and not reported as created
     *
     * @return void
     */
    public function testReusesIdenticalFile(): void
    {
        $this->mediaStorage->method('exists')->with($this->expectedPath)->willReturn(true);
        $this->mediaStorage->method('read')->with($this->expectedPath)->willReturn('bytes');
        $this->mediaStorage->expects(self::never())->method('write');

        $file = $this->store->store(5, 'desktop', $this->png(), 'bytes');

        self::assertSame($this->expectedPath, $file->getPath());
        self::assertFalse($file->isCreated());
    }

    /**
     * A name taken by other bytes is never overwritten
     *
     * @return void
     */
    public function testRefusesNameTakenByOtherBytes(): void
    {
        $this->mediaStorage->method('exists')->willReturn(true);
        $this->mediaStorage->method('read')->willReturn('other bytes');
        $this->mediaStorage->expects(self::never())->method('write');

        $this->expectException(CouldNotSaveException::class);
        $this->store->store(5, 'desktop', $this->png(), 'bytes');
    }

    /**
     * A storage failure is logged and reported without its details
     *
     * @return void
     */
    public function testStorageFailure(): void
    {
        $this->mediaStorage->method('exists')->willReturn(false);
        $this->mediaStorage->method('write')->willThrowException(new FileSystemException(__('disk /srv/media full')));
        $this->logger->expects(self::once())->method('error');

        try {
            $this->store->store(5, 'desktop', $this->png(), 'bytes');
            self::fail('The store must fail.');
        } catch (CouldNotSaveException $exception) {
            self::assertStringNotContainsString('/srv', $exception->getMessage());
        }
    }

    /**
     * Discarding deletes every path and only logs a failure
     *
     * @return void
     */
    public function testDiscardLogsFailures(): void
    {
        $deleted = [];
        $this->mediaStorage->method('delete')->willReturnCallback(function (string $path) use (&$deleted): void {
            if ($path === 'banner_slider/responsive/5/b.png') {
                throw new FileSystemException(__('locked'));
            }
            $deleted[] = $path;
        });
        $this->logger->expects(self::once())->method('error');

        $this->store->discard([
            'banner_slider/responsive/5/a.png',
            'banner_slider/responsive/5/b.png',
            'banner_slider/responsive/5/c.png',
        ]);

        self::assertSame(['banner_slider/responsive/5/a.png', 'banner_slider/responsive/5/c.png'], $deleted);
    }

    /**
     * The PNG format
     *
     * @return ImageFormat
     */
    private function png(): ImageFormat
    {
        return new ImageFormat('png', 'image/png', 'png');
    }
}
