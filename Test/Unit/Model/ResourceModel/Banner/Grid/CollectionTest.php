<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel\Banner\Grid;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\Grid\Collection;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(Collection::class)]
class CollectionTest extends TestCase
{
    /**
     * The slider filter keeps the rows of one slider, qualified with the main table alias
     *
     * @return void
     */
    public function testSliderFilter(): void
    {
        $conditions = [];
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(
            function (string $condition, mixed $value = null) use ($select, &$conditions): Select {
                $conditions[] = [$condition, $value];

                return $select;
            }
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($resourceConnection);
        ObjectManager::setInstance($objectManager);

        $collection = new Collection(
            $this->createMock(EntityFactoryInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FetchStrategyInterface::class),
            $this->createMock(ManagerInterface::class),
            BannerResource::TABLE_NAME
        );

        self::assertSame($collection, $collection->addSliderFilter(4));
        self::assertSame([['main_table.slider_id = ?', 4]], $conditions);
    }
}
