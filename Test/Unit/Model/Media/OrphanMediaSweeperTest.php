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
use Hryvinskyi\BannerSlider\Model\Media\MediaStorage;
use Hryvinskyi\BannerSlider\Model\Media\OrphanMediaSweeper;
use Hryvinskyi\BannerSlider\Model\Media\TextMediaReferenceExtractor;
use Hryvinskyi\BannerSlider\Model\Migration\LegacyMediaPathNormaliser;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\FileSystemException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(OrphanMediaSweeper::class)]
class OrphanMediaSweeperTest extends TestCase
{
    private const NOW = 1_800_000_000;
    private const DAY = 86400;

    /**
     * Rows each reference query returns, in order: banners, crops, variants, sliders
     *
     * @var list<list<array<string,string|null>>>
     */
    private array $rows = [];

    /**
     * Files in each package folder, with their modification time
     *
     * @var array<string,array<string,int>>
     */
    private array $files = [];

    /**
     * Paths deleted, in order
     *
     * @var list<string>
     */
    private array $deleted = [];

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * Unreferenced files older than a day are deleted; stored, legacy and quoted references are kept
     *
     * @return void
     */
    public function testDeletesOnlyOldUnreferencedFiles(): void
    {
        $this->rows = [
            [
                ['image' => 'banner_slider/image/2026/09/hero.jpg', 'video_path' => 'promo.mp4',
                    'content' => '<img src="{{media url=&quot;banner_slider/image/inline.png&quot;}}">'],
            ],
            [['source_image' => null, 'cropped_image' => 'banner_slider/responsive/5/desktop_new.jpg']],
            [['path' => 'banner_slider/responsive/5/desktop_new.webp']],
            [['custom_css' => '.x { background: url(/media/banner_slider/image/bg.png); }']],
        ];
        $old = self::NOW - 2 * self::DAY;
        $this->files = [
            'banner_slider/image' => [
                'banner_slider/image/2026/09/hero.jpg' => $old,
                'banner_slider/image/inline.png' => $old,
                'banner_slider/image/bg.png' => $old,
                'banner_slider/image/unused.jpg' => $old,
                'banner_slider/image/fresh-upload.jpg' => self::NOW - 60,
            ],
            'banner_slider/responsive' => [
                'banner_slider/responsive/5/desktop_new.jpg' => $old,
                'banner_slider/responsive/5/desktop_new.webp' => $old,
                'banner_slider/responsive/5/desktop_old.jpg' => $old,
            ],
            'banner_slider/video' => [
                'banner_slider/video/promo.mp4' => $old,
                'banner_slider/video/unused.mp4' => $old,
            ],
        ];

        $deleted = $this->sweeper()->sweep();

        $expected = [
            'banner_slider/image/unused.jpg',
            'banner_slider/responsive/5/desktop_old.jpg',
            'banner_slider/video/unused.mp4',
        ];
        self::assertSame($expected, $deleted);
        self::assertSame($expected, $this->deleted);
    }

    /**
     * A dry run lists the same files and deletes nothing
     *
     * @return void
     */
    public function testDryRun(): void
    {
        $this->files = ['banner_slider/image' => ['banner_slider/image/unused.jpg' => self::NOW - 2 * self::DAY]];

        self::assertSame(['banner_slider/image/unused.jpg'], $this->sweeper()->sweep(true));
        self::assertSame([], $this->deleted);
    }

    /**
     * A file within the grace period, or of unknown age, is kept
     *
     * @return void
     */
    public function testGracePeriodAndUnknownAge(): void
    {
        $this->files = ['banner_slider/image' => [
            'banner_slider/image/almost.jpg' => self::NOW - self::DAY + 1,
            'banner_slider/image/unknown.jpg' => -1,
        ]];

        self::assertSame([], $this->sweeper()->sweep());
    }

    /**
     * A failed delete is logged and the run goes on
     *
     * @return void
     */
    public function testFailedDeleteIsLogged(): void
    {
        $old = self::NOW - 2 * self::DAY;
        $this->files = ['banner_slider/image' => [
            'banner_slider/image/locked.jpg' => $old,
            'banner_slider/image/unused.jpg' => $old,
        ]];
        $sweeper = $this->sweeper();
        $this->logger->expects(self::once())->method('error');

        self::assertSame(['banner_slider/image/unused.jpg'], $sweeper->sweep());
    }

    /**
     * The sweeper over the doubles: roots image, responsive and video; the references from the queued rows
     *
     * @return OrphanMediaSweeper
     */
    private function sweeper(): OrphanMediaSweeper
    {
        $mediaStorage = $this->createMock(MediaStorage::class);
        $mediaStorage->method('listFiles')->willReturnCallback(
            fn (string $root): array => array_keys($this->files[$root] ?? [])
        );
        $mediaStorage->method('modifiedAt')->willReturnCallback(function (string $path): int {
            foreach ($this->files as $files) {
                if (($files[$path] ?? -1) >= 0) {
                    return $files[$path];
                }
            }
            throw new FileSystemException(__('no stat'));
        });
        $mediaStorage->method('delete')->willReturnCallback(function (string $path): void {
            if (str_contains($path, 'locked')) {
                throw new FileSystemException(__('locked'));
            }
            $this->deleted[] = $path;
        });
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn((new \DateTimeImmutable())->setTimestamp(self::NOW));
        $this->logger = $this->createMock(LoggerInterface::class);

        return new OrphanMediaSweeper(
            $mediaStorage,
            new MediaPaths([
                'image' => 'banner_slider/image',
                'responsive' => 'banner_slider/responsive',
                'video' => 'banner_slider/video',
            ]),
            $this->referenceIndex(),
            $clock,
            $this->logger,
            self::DAY
        );
    }

    /**
     * The real reference index over a connection double answering with the queued rows
     *
     * @return MediaReferenceIndex
     */
    private function referenceIndex(): MediaReferenceIndex
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnCallback(fn (): array => array_shift($this->rows) ?? []);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);
        $normaliser = new LegacyMediaPathNormaliser();

        return new MediaReferenceIndex($resourceConnection, $normaliser, new TextMediaReferenceExtractor($normaliser));
    }
}
