<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\BannerMediaCleaner;
use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\Media\MediaStorage;
use Hryvinskyi\BannerSlider\Model\ResourceModel\AfterCommitScheduler;
use Magento\Framework\Exception\FileSystemException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(BannerMediaCleaner::class)]
class BannerMediaCleanerTest extends TestCase
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
     * Work handed to the scheduler, not yet run
     *
     * @var list<callable>
     */
    private array $scheduled = [];

    /**
     * Folders deleted, in order
     *
     * @var list<string>
     */
    private array $deleted = [];

    /**
     * @var BannerMediaCleaner
     */
    private BannerMediaCleaner $cleaner;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->mediaStorage = $this->createMock(MediaStorage::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $scheduler = $this->createMock(AfterCommitScheduler::class);
        $scheduler->method('schedule')->willReturnCallback(function (callable $work): void {
            $this->scheduled[] = $work;
        });
        $this->cleaner = new BannerMediaCleaner(
            $this->mediaStorage,
            new MediaPaths(['image' => 'banner_slider/image', 'responsive' => 'banner_slider/responsive']),
            $scheduler,
            $this->logger
        );
    }

    /**
     * Only the banner's own crop folder is removed, and only when the scheduler runs the work after the commit
     *
     * @return void
     */
    public function testRemovesCropFolderOnlyAfterTheCommit(): void
    {
        $this->mediaStorage->method('deleteDirectory')->willReturnCallback(function (string $folder): void {
            $this->deleted[] = $folder;
        });
        $this->mediaStorage->expects(self::never())->method('delete');

        $this->cleaner->removeAfterCommit(7);
        self::assertSame([], $this->deleted, 'Nothing is deleted before the commit.');

        $this->runScheduled();
        self::assertSame(['banner_slider/responsive/7'], $this->deleted);
    }

    /**
     * Work the scheduler drops (a rolled-back transaction) deletes nothing
     *
     * @return void
     */
    public function testDroppedWorkKeepsTheFolder(): void
    {
        $this->mediaStorage->expects(self::never())->method('deleteDirectory');

        $this->cleaner->removeAfterCommit(7);

        self::assertCount(1, $this->scheduled);
    }

    /**
     * A failure is logged, not thrown: the banner row is already gone
     *
     * @return void
     */
    public function testFailureIsLogged(): void
    {
        $this->mediaStorage->method('deleteDirectory')->willThrowException(new FileSystemException(__('busy')));
        $this->logger->expects(self::once())->method('error');

        $this->cleaner->removeAfterCommit(7);
        $this->runScheduled();
    }

    /**
     * An id below 1 never names a folder, so the crop root itself is never touched
     *
     * @return void
     */
    public function testInvalidIdIsIgnored(): void
    {
        $this->mediaStorage->expects(self::never())->method('deleteDirectory');

        $this->cleaner->removeAfterCommit(0);

        self::assertSame([], $this->scheduled);
    }

    /**
     * Run the work handed to the scheduler, as the outermost commit does
     *
     * @return void
     */
    private function runScheduled(): void
    {
        foreach ($this->scheduled as $work) {
            $work();
        }
    }
}
