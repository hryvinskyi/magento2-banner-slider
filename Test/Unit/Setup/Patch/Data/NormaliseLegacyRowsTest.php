<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyMediaPathNormaliser;
use Hryvinskyi\BannerSlider\Model\Migration\SiteBaseUrls;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\MigrateCropVariants;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\NormaliseLegacyRows;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(NormaliseLegacyRows::class)]
class NormaliseLegacyRowsTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * Updates made on the connection: [table, data, where], expressions as their SQL text
     *
     * @var list<array{0: string, 1: array<mixed>, 2: mixed}>
     */
    private array $updates = [];

    /**
     * @var NormaliseLegacyRows
     */
    private NormaliseLegacyRows $patch;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('update')->willReturnCallback(
            function (string $table, array $data, mixed $where): int {
                $this->updates[] = [
                    $table,
                    array_map(
                        static fn (mixed $value): mixed => $value instanceof Expression ? (string)$value : $value,
                        $data
                    ),
                    $where,
                ];

                return 1;
            }
        );
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->willReturnArgument(0);
        $this->logger = $this->createMock(LoggerInterface::class);

        $siteBaseUrls = $this->createMock(SiteBaseUrls::class);
        $siteBaseUrls->method('getAll')->willReturn(['https://shop.test/', 'https://cdn.shop.test/media/']);

        $this->patch = new NormaliseLegacyRows(
            $setup,
            new LegacyMediaPathNormaliser(),
            $siteBaseUrls,
            new TypedRowFetcher(),
            $this->logger
        );
    }

    /**
     * Media values become media-relative, bare video names get the video folder, unsafe values are kept and logged
     *
     * @return void
     */
    public function testNormalisesMediaColumns(): void
    {
        $this->connection->method('fetchPairs')->willReturnOnConsecutiveCalls(
            ['1' => '/media/banner_slider/image/a.jpg', '2' => 'legacy_slider/image/b.png'],
            ['4' => '../app/etc/env.php'],
            ['4' => 'https://shop.test/media/banner_slider/responsive/4/d.png'],
            [],
            ['1' => 'promo.mp4', '2' => 'banner_slider/video/intro.mp4']
        );
        $this->connection->method('fetchCol')->willReturn([]);
        $this->connection->method('fetchOne')->willReturn('0');
        $warnings = [];
        $this->logger->method('warning')->willReturnCallback(
            function (string $message, array $context = []) use (&$warnings): void {
                $warnings[] = [$message, $context];
            }
        );

        self::assertSame($this->patch, $this->patch->apply());

        self::assertSame(
            [
                [
                    'hryvinskyi_banner_slider_banner',
                    ['image' => 'banner_slider/image/a.jpg'],
                    ['banner_id = ?' => 1],
                ],
                [
                    'hryvinskyi_banner_slider_responsive_crop',
                    ['cropped_image' => 'banner_slider/responsive/4/d.png'],
                    ['crop_id = ?' => 4],
                ],
                [
                    'hryvinskyi_banner_slider_banner',
                    ['video_path' => 'banner_slider/video/promo.mp4'],
                    ['banner_id = ?' => 1],
                ],
            ],
            $this->updates
        );
        self::assertSame(
            [[
                'Banner slider migration: hryvinskyi_banner_slider_responsive_crop.source_image values kept as stored;'
                    . ' they are not safe media-relative paths.',
                ['values_by_id' => [4 => '../app/etc/env.php']],
            ]],
            $warnings
        );
    }

    /**
     * A media URL on a host that is not one of the site's is kept as stored and logged
     *
     * @return void
     */
    public function testKeepsMediaUrlsOfOtherHosts(): void
    {
        $this->connection->method('fetchPairs')->willReturnOnConsecutiveCalls(
            [
                '1' => 'https://images.partner.test/media/banner_slider/image/a.jpg',
                '2' => 'https://CDN.shop.test:443/media/banner_slider/image/b.jpg',
            ],
            [],
            [],
            [],
            []
        );
        $this->connection->method('fetchCol')->willReturn([]);
        $this->connection->method('fetchOne')->willReturn('0');
        $this->logger->expects(self::once())->method('warning')->with(
            'Banner slider migration: hryvinskyi_banner_slider_banner.image values kept as stored; they are not safe '
            . 'media-relative paths.',
            ['values_by_id' => [1 => 'https://images.partner.test/media/banner_slider/image/a.jpg']]
        );

        $this->patch->apply();

        self::assertSame(
            [['hryvinskyi_banner_slider_banner', ['image' => 'banner_slider/image/b.jpg'], ['banner_id = ?' => 2]]],
            $this->updates
        );
    }

    /**
     * Rows whose window ends before it starts are disabled and closed at their start; unknown types are counted
     *
     * @return void
     */
    public function testClosesInvertedWindowsAndCountsUnknownTypes(): void
    {
        $this->connection->method('fetchPairs')->willReturn([]);
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(['3'], ['7', '9']);
        $this->connection->method('fetchOne')->willReturn('2');
        $warnings = [];
        $this->logger->method('warning')->willReturnCallback(
            function (string $message, array $context = []) use (&$warnings): void {
                $warnings[] = [$message, $context];
            }
        );

        $this->patch->apply();

        self::assertSame(
            [
                [
                    'hryvinskyi_banner_slider',
                    ['status' => 0, 'to_date' => 'from_date'],
                    ['slider_id IN (?)' => [3]],
                ],
                [
                    'hryvinskyi_banner_slider_banner',
                    ['status' => 0, 'to_date' => 'from_date'],
                    ['banner_id IN (?)' => [7, 9]],
                ],
            ],
            $this->updates
        );
        self::assertSame(
            [
                [
                    'Banner slider migration: hryvinskyi_banner_slider rows ended before they started;'
                        . ' disabled, end set to start.',
                    ['ids' => [3]],
                ],
                [
                    'Banner slider migration: hryvinskyi_banner_slider_banner rows ended before they started;'
                        . ' disabled, end set to start.',
                    ['ids' => [7, 9]],
                ],
                [
                    'Banner slider migration: 2 banners have an unknown type; they are read as custom banners.',
                    [],
                ],
            ],
            $warnings
        );
    }

    /**
     * It runs after the crop variants exist, so variant paths are normalised too
     *
     * @return void
     */
    public function testRunsAfterCropVariantMigration(): void
    {
        self::assertSame([MigrateCropVariants::class], NormaliseLegacyRows::getDependencies());
        self::assertSame([], $this->patch->getAliases());
    }
}
