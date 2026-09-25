<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel\Slider\Grid;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\Grid\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\VisibilityLinks;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(Collection::class)]
class CollectionTest extends TestCase
{
    /**
     * Every loaded listing row carries its store and customer group id lists
     *
     * @return void
     */
    public function testLoadAttachesStoreAndGroupLists(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::exactly(2))->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['slider_id' => '1', 'store_id' => '0'], ['slider_id' => '2', 'store_id' => '1']],
            [['slider_id' => '2', 'customer_group_id' => '3']]
        );

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $resource = $this->createMock(SliderResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getIdFieldName')->willReturn(SliderInterface::SLIDER_ID);
        $resource->method('getTable')->willReturnArgument(0);

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($resourceConnection);
        $objectManager->method('create')->willReturn($resource);
        ObjectManager::setInstance($objectManager);

        $fetchStrategy = $this->createMock(FetchStrategyInterface::class);
        $fetchStrategy->method('fetchAll')->willReturn([
            ['slider_id' => '1', 'name' => 'Home'],
            ['slider_id' => '2', 'name' => 'Sidebar'],
        ]);
        $entityFactory = $this->createMock(EntityFactoryInterface::class);
        $entityFactory->method('create')->willReturnCallback(
            fn (): Document => new Document($this->createMock(AttributeValueFactory::class))
        );

        $collection = new Collection(
            $entityFactory,
            $this->createMock(LoggerInterface::class),
            $fetchStrategy,
            $this->createMock(ManagerInterface::class),
            new VisibilityLinks($resourceConnection)
        );
        $rows = array_values($collection->load()->getItems());

        self::assertCount(2, $rows);
        self::assertSame([0], $rows[0]->getData(SliderInterface::STORE_IDS));
        self::assertSame([], $rows[0]->getData(SliderInterface::CUSTOMER_GROUP_IDS));
        self::assertSame([1], $rows[1]->getData(SliderInterface::STORE_IDS));
        self::assertSame([3], $rows[1]->getData(SliderInterface::CUSTOMER_GROUP_IDS));
    }
}
