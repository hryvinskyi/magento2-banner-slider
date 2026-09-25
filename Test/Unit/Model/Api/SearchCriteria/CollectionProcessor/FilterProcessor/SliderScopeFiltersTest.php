<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Api\SearchCriteria\CollectionProcessor\FilterProcessor;

use Hryvinskyi\BannerSlider\Model\Api\SearchCriteria\CollectionProcessor\FilterProcessor\FilterIdList;
use Hryvinskyi\BannerSlider\Model\Api\SearchCriteria\CollectionProcessor\FilterProcessor\SliderCustomerGroupFilter;
use Hryvinskyi\BannerSlider\Model\Api\SearchCriteria\CollectionProcessor\FilterProcessor\SliderStoreFilter;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\Collection;
use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection\AbstractDb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterIdList::class)]
#[CoversClass(SliderStoreFilter::class)]
#[CoversClass(SliderCustomerGroupFilter::class)]
class SliderScopeFiltersTest extends TestCase
{
    /**
     * Ids are read from one value, a comma list or an array
     *
     * @return void
     */
    public function testFilterIdList(): void
    {
        $list = new FilterIdList();

        self::assertSame([3], $list->read(new Filter(['value' => '3'])));
        self::assertSame([1, 2], $list->read(new Filter(['value' => '1, 2,x,-1,2'])));
        self::assertSame([0, 4], $list->read(new Filter(['value' => [0, '4', 'a', null]])));
        self::assertSame([], $list->read(new Filter()));
    }

    /**
     * The store filter narrows the slider collection through its store links
     *
     * @return void
     */
    public function testStoreFilter(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('addStoreFilter')->with([1, 2])->willReturnSelf();

        self::assertTrue(
            (new SliderStoreFilter(new FilterIdList()))->apply(new Filter(['value' => '1,2']), $collection)
        );
    }

    /**
     * The customer group filter narrows the slider collection through its group links
     *
     * @return void
     */
    public function testCustomerGroupFilter(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('addCustomerGroupFilter')->with([0])->willReturnSelf();

        self::assertTrue(
            (new SliderCustomerGroupFilter(new FilterIdList()))->apply(new Filter(['value' => '0']), $collection)
        );
    }

    /**
     * The condition decides whether the ids are kept or excluded; no condition keeps them
     *
     * @param string|null $condition
     * @param bool $expected
     * @return void
     */
    #[TestWith([null, false])]
    #[TestWith(['eq', false])]
    #[TestWith(['in', false])]
    #[TestWith(['NEQ', true])]
    #[TestWith(['nin', true])]
    public function testConditionPolarity(?string $condition, bool $expected): void
    {
        $filter = new Filter(['field' => 'store_id', 'value' => '1']);
        if ($condition !== null) {
            $filter->setConditionType($condition);
        }

        self::assertSame($expected, (new FilterIdList())->excludes($filter));
    }

    /**
     * A condition an id list cannot answer is refused, not read as eq
     *
     * @param string $condition
     * @return void
     */
    #[TestWith(['like'])]
    #[TestWith(['gt'])]
    #[TestWith(['finset'])]
    public function testUnsupportedConditionIsRefused(string $condition): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The "store_id" filter supports the conditions eq, in, neq, nin, got "' . $condition . '".'
        );

        (new FilterIdList())->excludes(
            (new Filter(['field' => 'store_id', 'value' => '1']))->setConditionType($condition)
        );
    }

    /**
     * Excluding conditions narrow the collection to the other sliders
     *
     * @return void
     */
    public function testExcludingFilters(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('excludeStores')->with([1, 2])->willReturnSelf();
        $collection->expects(self::once())->method('excludeCustomerGroups')->with([0])->willReturnSelf();
        $collection->expects(self::never())->method('addStoreFilter');
        $collection->expects(self::never())->method('addCustomerGroupFilter');

        self::assertTrue((new SliderStoreFilter(new FilterIdList()))->apply(
            (new Filter(['value' => '1,2']))->setConditionType('nin'),
            $collection
        ));
        self::assertTrue((new SliderCustomerGroupFilter(new FilterIdList()))->apply(
            (new Filter(['value' => '0']))->setConditionType('neq'),
            $collection
        ));
    }

    /**
     * Another collection is refused
     *
     * @return void
     */
    public function testForeignCollectionIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SliderStoreFilter(new FilterIdList()))->apply(new Filter(), $this->createMock(AbstractDb::class));
    }
}
