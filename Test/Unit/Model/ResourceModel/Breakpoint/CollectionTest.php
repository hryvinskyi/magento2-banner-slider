<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel\Breakpoint;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint as BreakpointResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\Collection;
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
     * Orders added to the select, in order
     *
     * @var list<mixed>
     */
    private array $orders = [];

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
        $select->method('order')->willReturnCallback(
            function (mixed $spec) use ($select): Select {
                $this->orders[] = $spec;

                return $select;
            }
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $resource = $this->createMock(BreakpointResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn(BreakpointResource::TABLE_NAME);
        $resource->method('getIdFieldName')->willReturn('breakpoint_id');

        $this->collection = new Collection(
            $this->createMock(EntityFactoryInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FetchStrategyInterface::class),
            $this->createMock(ManagerInterface::class),
            $connection,
            $resource
        );
    }

    /**
     * Breakpoints of several sliders, enabled, widest first; an empty slider list keeps none
     *
     * @return void
     */
    public function testRenderingQueryOfSeveralSliders(): void
    {
        $this->collection->addSliderIdsFilter([1, 2])->addEnabledFilter()->orderForRendering();
        $this->collection->addSliderIdsFilter([]);

        self::assertSame(
            [
                ['main_table.slider_id IN (?)', [1, 2]],
                ['main_table.status = ?', 1],
                ['1 = 0', null],
            ],
            $this->where
        );
        self::assertSame(
            ['main_table.min_width DESC', 'main_table.sort_order ASC', 'main_table.breakpoint_id ASC'],
            $this->orders
        );
    }
}
