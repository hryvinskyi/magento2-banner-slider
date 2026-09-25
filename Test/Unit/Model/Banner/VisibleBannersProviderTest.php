<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Banner;

use DateTimeImmutable;
use Hryvinskyi\BannerSlider\Model\Banner\VisibleBannersProvider;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\DataObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VisibleBannersProvider::class)]
class VisibleBannersProviderTest extends TestCase
{
    /**
     * Enabled banners of the slider active at the moment, by position then id
     *
     * @return void
     */
    public function testGetForSliderBuildsTheQuery(): void
    {
        $at = new DateTimeImmutable('2026-09-25 10:00:00');
        $first = $this->createMock(BannerInterface::class);
        $second = $this->createMock(BannerInterface::class);
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('addSliderFilter')->with(4)->willReturnSelf();
        $collection->expects(self::once())->method('addEnabledFilter')->willReturnSelf();
        $collection->expects(self::once())->method('addActiveAtFilter')->with($at)->willReturnSelf();
        $collection->expects(self::once())->method('orderByPosition')->willReturnSelf();
        $collection->method('getItems')->willReturn([10 => $first, 11 => new DataObject(), 12 => $second]);
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        self::assertSame([$first, $second], (new VisibleBannersProvider($factory))->getForSlider(4, $at));
    }

    /**
     * An id below 1 cannot be a slider: no query
     *
     * @return void
     */
    public function testInvalidSliderIdYieldsNothing(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');

        self::assertSame([], (new VisibleBannersProvider($factory))->getForSlider(0, new DateTimeImmutable()));
    }
}
