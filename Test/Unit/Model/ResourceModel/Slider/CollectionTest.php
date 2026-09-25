<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel\Slider;

use DateTimeImmutable;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ActiveWindowCondition;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\VisibilityLinks;
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
     * Conditions added to any select, in order
     *
     * @var list<array{0: string, 1: mixed}>
     */
    private array $where = [];

    /**
     * Orders added to any select, in order
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
        $select->method('assemble')->willReturn('SUBQUERY');
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

        $resource = $this->createMock(SliderResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn(SliderResource::TABLE_NAME);
        $resource->method('getTable')->willReturnArgument(0);
        $resource->method('getIdFieldName')->willReturn('slider_id');

        $this->collection = new Collection(
            $this->createMock(EntityFactoryInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FetchStrategyInterface::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(VisibilityLinks::class),
            new ActiveWindowCondition(),
            $connection,
            $resource
        );
    }

    /**
     * The lowest priority value comes first, then the lowest id
     *
     * @return void
     */
    public function testOrderByPriorityIsAscending(): void
    {
        $this->collection->orderByPriority();

        self::assertSame(['main_table.priority ASC', 'main_table.slider_id ASC'], $this->orders);
    }

    /**
     * Visibility checks store rows for the store or store 0 and group rows unless all groups are allowed
     *
     * @return void
     */
    public function testVisibilityFilter(): void
    {
        $this->collection->addVisibilityFilter(3, 1);

        self::assertSame(
            [
                ['store_link.slider_id = main_table.slider_id', null],
                ['store_link.store_id IN (?)', [0, 3]],
                ['EXISTS (SUBQUERY)', null],
                ['group_link.slider_id = main_table.slider_id', null],
                ['group_link.customer_group_id IN (?)', [1]],
                ['(main_table.all_customer_groups = 1 OR EXISTS (SUBQUERY))', null],
            ],
            $this->where
        );
    }

    /**
     * Without groups only sliders for all groups match
     *
     * @return void
     */
    public function testCustomerGroupFilterWithoutGroups(): void
    {
        $this->collection->addCustomerGroupFilter([]);

        self::assertSame([['(main_table.all_customer_groups = 1)', null]], $this->where);
    }

    /**
     * Excluding store views keeps the sliders without a store row for them or for store 0
     *
     * @return void
     */
    public function testExcludeStores(): void
    {
        $this->collection->excludeStores([3]);

        self::assertSame(
            [
                ['store_link.slider_id = main_table.slider_id', null],
                ['store_link.store_id IN (?)', [0, 3]],
                ['NOT EXISTS (SUBQUERY)', null],
            ],
            $this->where
        );
    }

    /**
     * Excluding customer groups keeps the sliders that are neither for all groups nor linked to one of them
     *
     * @return void
     */
    public function testExcludeCustomerGroups(): void
    {
        $this->collection->excludeCustomerGroups([1, 2]);
        $this->collection->excludeCustomerGroups([]);

        self::assertSame(
            [
                ['group_link.slider_id = main_table.slider_id', null],
                ['group_link.customer_group_id IN (?)', [1, 2]],
                ['NOT (main_table.all_customer_groups = 1 OR EXISTS (SUBQUERY))', null],
                ['NOT (main_table.all_customer_groups = 1)', null],
            ],
            $this->where
        );
    }

    /**
     * Enabled, location and active-window filters target the main table
     *
     * @return void
     */
    public function testStorefrontFilters(): void
    {
        $this->collection->addEnabledFilter()
            ->addLocationFilter('home')
            ->addActiveAtFilter(new DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC')));

        self::assertSame(
            [
                ['main_table.status = ?', 1],
                ['main_table.location = ?', 'home'],
                ['(main_table.from_date IS NULL OR main_table.from_date <= ?)', '2026-01-01 00:00:00'],
                ['(main_table.to_date IS NULL OR main_table.to_date >= ?)', '2026-01-01 00:00:00'],
            ],
            $this->where
        );
    }
}
