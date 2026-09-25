<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\VariantRows;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariantRows::class)]
class VariantRowsTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var VariantRows
     */
    private VariantRows $rows;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->rows = new VariantRows($resourceConnection);
    }

    /**
     * Stored rows become variants; unusable rows are left out and qualities brought into range
     *
     * @return void
     */
    public function testFetchByCropIds(): void
    {
        $this->connection->method('fetchAll')->willReturn([
            ['crop_id' => '1', 'format' => 'avif', 'quality' => '80', 'path' => null],
            ['crop_id' => '1', 'format' => 'webp', 'quality' => '150', 'path' => 'banner_slider/responsive/1/a.webp'],
            ['crop_id' => '2', 'format' => 'WEBP!', 'quality' => '85', 'path' => null],
            ['crop_id' => '2', 'format' => 'webp', 'quality' => '85', 'path' => '/abs/a.webp'],
            ['crop_id' => '2', 'format' => 'avif', 'quality' => 'high', 'path' => null],
        ]);

        $variants = $this->rows->fetchByCropIds([1, 2, 3]);

        self::assertSame([1, 2, 3], array_keys($variants));
        self::assertCount(2, $variants[1]);
        self::assertSame('avif', $variants[1][0]->getFormat());
        self::assertNull($variants[1][0]->getPath());
        self::assertSame(100, $variants[1][1]->getQuality());
        self::assertSame('banner_slider/responsive/1/a.webp', $variants[1][1]->getPath());
        self::assertSame([], $variants[2]);
        self::assertSame([], $variants[3]);
    }

    /**
     * Variants are upserted and other formats of the crop deleted
     *
     * @return void
     */
    public function testReplace(): void
    {
        $this->connection->expects(self::once())->method('delete')
            ->with(VariantRows::TABLE, ['crop_id = ?' => 4, 'format NOT IN (?)' => ['webp', 'avif']]);
        $this->connection->expects(self::once())->method('insertOnDuplicate')->with(
            VariantRows::TABLE,
            [
                ['crop_id' => 4, 'format' => 'webp', 'quality' => 85, 'path' => 'banner_slider/responsive/4/a.webp'],
                ['crop_id' => 4, 'format' => 'avif', 'quality' => 60, 'path' => null],
            ],
            ['quality', 'path']
        );

        $this->rows->replace(
            4,
            [new CropVariant('webp', 85, 'banner_slider/responsive/4/a.webp'), new CropVariant('avif', 60)]
        );
    }

    /**
     * No variants deletes every variant row of the crop
     *
     * @return void
     */
    public function testReplaceWithNone(): void
    {
        $this->connection->expects(self::once())->method('delete')
            ->with(VariantRows::TABLE, ['crop_id = ?' => 4]);
        $this->connection->expects(self::never())->method('insertOnDuplicate');

        $this->rows->replace(4, []);
    }
}
