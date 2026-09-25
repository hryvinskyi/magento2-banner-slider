<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\VariantRows;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(Collection::class)]
class CollectionTest extends TestCase
{
    /**
     * Conditions added to the select, in order
     *
     * @var list<array{0: string, 1: mixed}>
     */
    private array $where = [];

    /**
     * Joins added to the select, in order
     *
     * @var list<array{0: mixed, 1: mixed, 2: mixed}>
     */
    private array $joins = [];

    /**
     * @var Collection
     */
    private Collection $collection;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(
            function (string $condition, mixed $value = null) use ($select): Select {
                $this->where[] = [$condition, $value];

                return $select;
            }
        );
        $select->method('join')->willReturnCallback(
            function (mixed $table, mixed $condition, mixed $columns) use ($select): Select {
                $this->joins[] = [$table, $condition, $columns];

                return $select;
            }
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $resource = $this->createMock(ResponsiveCropResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn(ResponsiveCropResource::TABLE_NAME);
        $resource->method('getTable')->willReturnArgument(0);
        $resource->method('getIdFieldName')->willReturn('crop_id');

        $this->collection = new Collection(
            $this->createMock(EntityFactoryInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FetchStrategyInterface::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(VariantRows::class),
            $connection,
            $resource
        );
    }

    /**
     * Crops of breakpoints, only generated ones; an empty id list keeps none
     *
     * @return void
     */
    public function testBreakpointAndGeneratedFilters(): void
    {
        $this->collection->addBreakpointIdsFilter([3, 4])->addGeneratedFilter()->addBreakpointIdsFilter([]);

        self::assertSame(
            [
                ['main_table.breakpoint_id IN (?)', [3, 4]],
                ['main_table.cropped_image IS NOT NULL', null],
                ["main_table.cropped_image <> ''", null],
                ['1 = 0', null],
            ],
            $this->where
        );
    }

    /**
     * The banner's slider id is joined under its own data key
     *
     * @return void
     */
    public function testJoinBannerSliderId(): void
    {
        $this->collection->joinBannerSliderId();

        self::assertSame(
            [[
                ['banner' => 'hryvinskyi_banner_slider_banner'],
                'banner.banner_id = main_table.banner_id',
                ['banner_slider_id' => 'slider_id'],
            ]],
            $this->joins
        );
    }
}
