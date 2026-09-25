<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel\Slider;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\VisibilityLinks;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Visibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(VisibilityLinks::class)]
class VisibilityLinksTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var VisibilityLinks
     */
    private VisibilityLinks $links;

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

        $this->links = new VisibilityLinks($resourceConnection);
    }

    /**
     * Every requested slider is a key, with its ids in stored order
     *
     * @return void
     */
    public function testFetchStoreIds(): void
    {
        $this->connection->method('fetchAll')->willReturn([
            ['slider_id' => '1', 'store_id' => '0'],
            ['slider_id' => '1', 'store_id' => '2'],
            ['slider_id' => '9', 'store_id' => '3'],
        ]);

        self::assertSame([1 => [0, 2], 4 => []], $this->links->fetchStoreIds([1, 4]));
    }

    /**
     * No slider means no query
     *
     * @return void
     */
    public function testFetchNothing(): void
    {
        $this->connection->expects(self::never())->method('fetchAll');

        self::assertSame([], $this->links->fetchCustomerGroupIds([]));
    }

    /**
     * Both lists are attached to every row, in two queries
     *
     * @return void
     */
    public function testAttach(): void
    {
        $this->connection->expects(self::exactly(2))->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['slider_id' => '1', 'store_id' => '0'], ['slider_id' => '2', 'store_id' => '1']],
            [['slider_id' => '2', 'customer_group_id' => '0'], ['slider_id' => '2', 'customer_group_id' => '1']]
        );
        $first = new DataObject([SliderInterface::SLIDER_ID => '1']);
        $second = new DataObject([SliderInterface::SLIDER_ID => 2]);

        $this->links->attach([$first, $second]);

        self::assertSame([0], $first->getData(SliderInterface::STORE_IDS));
        self::assertSame([], $first->getData(SliderInterface::CUSTOMER_GROUP_IDS));
        self::assertSame([1], $second->getData(SliderInterface::STORE_IDS));
        self::assertSame([0, 1], $second->getData(SliderInterface::CUSTOMER_GROUP_IDS));
    }

    /**
     * Rows without a slider id cause no query
     *
     * @return void
     */
    public function testAttachWithoutIds(): void
    {
        $this->connection->expects(self::never())->method('fetchAll');

        $this->links->attach([new DataObject()]);
    }

    /**
     * Unwanted rows are deleted and the wanted ones upserted
     *
     * @return void
     */
    public function testReplace(): void
    {
        $deletes = [];
        $inserts = [];
        $this->connection->method('delete')->willReturnCallback(
            function (string $table, array $where) use (&$deletes): int {
                $deletes[] = [$table, $where];

                return 0;
            }
        );
        $this->connection->method('insertOnDuplicate')->willReturnCallback(
            function (string $table, array $rows) use (&$inserts): int {
                $inserts[] = [$table, $rows];

                return count($rows);
            }
        );

        $this->links->replace(5, new Visibility([0, 2], [1], false));

        self::assertSame(
            [
                [VisibilityLinks::STORE_TABLE, ['slider_id = ?' => 5, 'store_id NOT IN (?)' => [0, 2]]],
                [
                    VisibilityLinks::CUSTOMER_GROUP_TABLE,
                    ['slider_id = ?' => 5, 'customer_group_id NOT IN (?)' => [1]],
                ],
            ],
            $deletes
        );
        self::assertSame(
            [
                [
                    VisibilityLinks::STORE_TABLE,
                    [['slider_id' => 5, 'store_id' => 0], ['slider_id' => 5, 'store_id' => 2]],
                ],
                [VisibilityLinks::CUSTOMER_GROUP_TABLE, [['slider_id' => 5, 'customer_group_id' => 1]]],
            ],
            $inserts
        );
    }

    /**
     * With all customer groups the slider keeps no group rows
     *
     * @return void
     */
    public function testReplaceWithAllGroupsClearsGroupRows(): void
    {
        $deletes = [];
        $this->connection->method('delete')->willReturnCallback(
            function (string $table, array $where) use (&$deletes): int {
                $deletes[] = [$table, $where];

                return 0;
            }
        );
        $this->connection->expects(self::once())->method('insertOnDuplicate')
            ->with(VisibilityLinks::STORE_TABLE);

        $this->links->replace(5, new Visibility([0], [], true));

        self::assertSame([VisibilityLinks::CUSTOMER_GROUP_TABLE, ['slider_id = ?' => 5]], $deletes[1]);
    }
}
