<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel\Banner;

use DateTimeImmutable;
use DateTimeZone;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ActiveWindowCondition;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\Collection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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
     * @var Select&MockObject
     */
    private MockObject $select;

    /**
     * @var ActiveWindowCondition&MockObject
     */
    private MockObject $activeWindowCondition;

    /**
     * @var Collection
     */
    private Collection $collection;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnCallback(
            function (string $condition, mixed $value = null): Select {
                $this->where[] = [$condition, $value];

                return $this->select;
            }
        );
        $this->select->method('order')->willReturnCallback(
            function (mixed $spec): Select {
                $this->orders[] = $spec;

                return $this->select;
            }
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->select);
        $resource = $this->createMock(BannerResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn(BannerResource::TABLE_NAME);
        $resource->method('getIdFieldName')->willReturn('banner_id');
        $this->activeWindowCondition = $this->createMock(ActiveWindowCondition::class);

        $this->collection = new Collection(
            $this->createMock(EntityFactoryInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FetchStrategyInterface::class),
            $this->createMock(ManagerInterface::class),
            $this->activeWindowCondition,
            $connection,
            $resource
        );
    }

    /**
     * The storefront query: one slider, enabled, active at the moment, in slide order
     *
     * @return void
     */
    public function testStorefrontQuery(): void
    {
        $at = new DateTimeImmutable('2026-09-25 10:00:00', new DateTimeZone('UTC'));
        $this->activeWindowCondition->expects(self::once())->method('apply')
            ->with($this->select, $at, 'main_table');

        $result = $this->collection->addSliderFilter(3)
            ->addEnabledFilter()
            ->addActiveAtFilter($at)
            ->orderByPosition();

        self::assertSame($this->collection, $result);
        self::assertSame([['main_table.slider_id = ?', 3], ['main_table.status = ?', 1]], $this->where);
        self::assertSame(['main_table.position ASC', 'main_table.banner_id ASC'], $this->orders);
    }
}
