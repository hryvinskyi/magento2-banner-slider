<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\Media\MediaReferenceIndex;
use Hryvinskyi\BannerSlider\Model\Media\MediaReferenceSnapshot;
use Hryvinskyi\BannerSlider\Model\Media\MediaStorage;
use Hryvinskyi\BannerSlider\Model\Media\ObsoleteMediaRemover;
use Magento\Framework\Exception\FileSystemException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ObsoleteMediaRemover::class)]
class ObsoleteMediaRemoverTest extends TestCase
{
    /**
     * Paths deleted, in order
     *
     * @var list<string>
     */
    private array $deleted = [];

    /**
     * @var MediaStorage&MockObject
     */
    private MockObject $mediaStorage;

    /**
     * @var MediaReferenceIndex&MockObject
     */
    private MockObject $referenceIndex;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var ObsoleteMediaRemover
     */
    private ObsoleteMediaRemover $remover;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->mediaStorage = $this->createMock(MediaStorage::class);
        $this->mediaStorage->method('delete')->willReturnCallback(function (string $path): void {
            $this->deleted[] = $path;
        });
        $this->referenceIndex = $this->createMock(MediaReferenceIndex::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->remover = new ObsoleteMediaRemover(
            $this->mediaStorage,
            new MediaPaths([
                'image' => 'banner_slider/image',
                'responsive' => 'banner_slider/responsive',
                'video' => 'banner_slider/video',
            ]),
            $this->referenceIndex,
            $this->logger
        );
    }

    /**
     * Only unreferenced files under the crop output folder that the save did not write again are deleted
     *
     * @return void
     */
    public function testDeletesOnlyObsoleteCropOutput(): void
    {
        $this->referenceIndex->expects(self::once())
            ->method('snapshot')
            ->willReturn(new MediaReferenceSnapshot(['banner_slider/responsive/5/shared_111.jpg']));

        $this->remover->remove(
            [
                'banner_slider/responsive/5/desktop_aaa.jpg',
                '/banner_slider/responsive/5/desktop_aaa.webp',
                'banner_slider/responsive/5/same_222.jpg',
                'banner_slider/responsive/5/shared_111.jpg',
                'banner_slider/image/source.jpg',
                'legacy_slider/image/legacy.jpg',
                'banner_slider/responsive/../../app/etc/env.php',
                'banner_slider/responsive/5/desktop_aaa.jpg',
            ],
            ['banner_slider/responsive/5/same_222.jpg']
        );

        self::assertSame(
            ['banner_slider/responsive/5/desktop_aaa.jpg', 'banner_slider/responsive/5/desktop_aaa.webp'],
            $this->deleted
        );
    }

    /**
     * Nothing left to consider means no reference query at all
     *
     * @return void
     */
    public function testNoCandidateReadsNoReferences(): void
    {
        $this->referenceIndex->expects(self::never())->method('snapshot');

        $this->remover->remove(['banner_slider/image/source.jpg', 'banner_slider/responsive/1/a.jpg'], [
            'banner_slider/responsive/1/a.jpg',
        ]);
        $this->remover->remove([]);

        self::assertSame([], $this->deleted);
    }

    /**
     * A file that cannot be deleted is logged and the others are still deleted
     *
     * @return void
     */
    public function testDeleteFailureIsLoggedNotThrown(): void
    {
        $this->referenceIndex->method('snapshot')->willReturn(new MediaReferenceSnapshot([]));
        $this->mediaStorage = $this->createMock(MediaStorage::class);
        $failure = new FileSystemException(__('Permission denied'));
        $this->mediaStorage->method('delete')->willReturnCallback(function (string $path) use ($failure): void {
            if (str_ends_with($path, 'locked.jpg')) {
                throw $failure;
            }
            $this->deleted[] = $path;
        });
        $remover = new ObsoleteMediaRemover(
            $this->mediaStorage,
            new MediaPaths(['responsive' => 'banner_slider/responsive']),
            $this->referenceIndex,
            $this->logger
        );
        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('banner_slider/responsive/1/locked.jpg'),
                ['exception' => $failure]
            );

        $remover->remove(['banner_slider/responsive/1/locked.jpg', 'banner_slider/responsive/1/free.jpg']);

        self::assertSame(['banner_slider/responsive/1/free.jpg'], $this->deleted);
    }

    /**
     * When the references cannot be read, nothing is deleted and the failure is logged
     *
     * @return void
     */
    public function testUnreadableReferencesKeepEveryFile(): void
    {
        $failure = new \RuntimeException('Connection lost');
        $this->referenceIndex->method('snapshot')->willThrowException($failure);
        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('could not be read'),
                ['exception' => $failure, 'paths' => ['banner_slider/responsive/1/a.jpg']]
            );

        $this->remover->remove(['banner_slider/responsive/1/a.jpg']);

        self::assertSame([], $this->deleted);
    }

    /**
     * Paths spelled with doubled slashes or `.` segments are compared, kept and deleted in their canonical form
     *
     * @return void
     */
    public function testComparesCanonicalPaths(): void
    {
        $this->referenceIndex->expects(self::once())
            ->method('snapshot')
            ->willReturn(new MediaReferenceSnapshot(['banner_slider/responsive//5/./used_1.jpg']));

        $this->remover->remove(
            [
                'banner_slider/responsive/5/used_1.jpg',
                'banner_slider//responsive/5/kept_2.jpg',
                'banner_slider/responsive/./5//old_3.jpg',
            ],
            ['banner_slider/responsive/5/./kept_2.jpg']
        );

        self::assertSame(['banner_slider/responsive/5/old_3.jpg'], $this->deleted);
    }
}
