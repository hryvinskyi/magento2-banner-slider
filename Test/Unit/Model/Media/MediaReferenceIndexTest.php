<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\MediaReferenceIndex;
use Hryvinskyi\BannerSlider\Model\Media\MediaReferenceSnapshot;
use Hryvinskyi\BannerSlider\Model\Media\TextMediaReferenceExtractor;
use Hryvinskyi\BannerSlider\Model\Migration\LegacyMediaPathNormaliser;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaReferenceIndex::class)]
#[CoversClass(MediaReferenceSnapshot::class)]
class MediaReferenceIndexTest extends TestCase
{
    /**
     * Tables read, in order
     *
     * @var list<string>
     */
    private array $tables = [];

    /**
     * Rows each query returns, in order
     *
     * @var list<list<mixed>>
     */
    private array $results = [];

    /**
     * Stored values of every table are normalised and quoted media paths in content and CSS are included
     *
     * @return void
     */
    public function testSnapshotReadsEveryReference(): void
    {
        $this->results = [
            [
                [
                    'image' => '/banner_slider/image/2026/09/hero.jpg',
                    'video_path' => 'promo.mp4',
                    'content' => '<img src="{{media url=&quot;wysiwyg/badge.png&quot;}}">',
                ],
                ['image' => 'https://shop.test/pub/media/legacy_slider/image/old.jpg', 'video_path' => null,
                    'content' => null],
                ['image' => '../../app/etc/env.php', 'video_path' => 'https://youtu.be/x', 'content' => ''],
                'not a row',
            ],
            [
                ['source_image' => 'hryvinskyi/banner_slider/src.jpg',
                    'cropped_image' => 'banner_slider/responsive/5/desktop_abc.jpg'],
            ],
            [
                ['path' => 'banner_slider/responsive/5/desktop_abc.webp'],
            ],
            [
                ['custom_css' => '.banner-slider-1 { background: url(/media/banner_slider/image/bg.png); }'],
            ],
        ];

        $snapshot = $this->index()->snapshot();

        self::assertSame(
            [
                'banner_slider/image/2026/09/hero.jpg',
                'banner_slider/image/bg.png',
                'banner_slider/responsive/5/desktop_abc.jpg',
                'banner_slider/responsive/5/desktop_abc.webp',
                'banner_slider/video/promo.mp4',
                'hryvinskyi/banner_slider/src.jpg',
                'legacy_slider/image/old.jpg',
                'wysiwyg/badge.png',
            ],
            $snapshot->getPaths()
        );
        self::assertSame(
            [
                'hryvinskyi_banner_slider_banner',
                'hryvinskyi_banner_slider_responsive_crop',
                'hryvinskyi_banner_slider_crop_variant',
                'hryvinskyi_banner_slider',
            ],
            $this->tables
        );
        self::assertTrue($snapshot->isReferenced('/banner_slider/video/promo.mp4'));
        self::assertTrue($snapshot->isReferenced('banner_slider/image/bg.png'));
        self::assertFalse($snapshot->isReferenced('promo.mp4'));
        self::assertFalse($snapshot->isReferenced('banner_slider/responsive/5/tablet_abc.jpg'));
    }

    /**
     * Empty tables give an empty snapshot
     *
     * @return void
     */
    public function testEmptySnapshot(): void
    {
        $snapshot = $this->index()->snapshot();

        self::assertSame([], $snapshot->getPaths());
        self::assertFalse($snapshot->isReferenced(''));
    }

    /**
     * A stored path spelled with doubled slashes or `.` segments protects the file it names
     *
     * @return void
     */
    public function testSnapshotComparesCanonicalPaths(): void
    {
        $snapshot = new MediaReferenceSnapshot([
            'banner_slider/image//a.jpg',
            '/banner_slider/./image/b.jpg',
            'hryvinskyi//banner_slider/c.jpg',
        ]);

        self::assertTrue($snapshot->isReferenced('banner_slider/image/a.jpg'));
        self::assertTrue($snapshot->isReferenced('banner_slider/image/b.jpg'));
        self::assertTrue($snapshot->isReferenced('/hryvinskyi/banner_slider/c.jpg'));
        self::assertTrue($snapshot->isReferenced('banner_slider/image/./a.jpg'));
        self::assertFalse($snapshot->isReferenced('banner_slider/image/d.jpg'));
        self::assertSame(
            ['banner_slider/image/a.jpg', 'banner_slider/image/b.jpg', 'hryvinskyi/banner_slider/c.jpg'],
            $snapshot->getPaths()
        );
    }

    /**
     * The index over a connection double answering with the queued results
     *
     * @return MediaReferenceIndex
     */
    private function index(): MediaReferenceIndex
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnCallback(function (mixed $table) use ($select): Select {
            $this->tables[] = is_string($table) ? $table : '';

            return $select;
        });
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnCallback(fn (): array => array_shift($this->results) ?? []);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);
        $normaliser = new LegacyMediaPathNormaliser();

        return new MediaReferenceIndex(
            $resourceConnection,
            $normaliser,
            new TextMediaReferenceExtractor($normaliser)
        );
    }
}
