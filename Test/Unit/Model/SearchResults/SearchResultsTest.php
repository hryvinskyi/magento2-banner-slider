<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\SearchResults;

use Hryvinskyi\BannerSlider\Model\SearchResults\AbstractSearchResults;
use Hryvinskyi\BannerSlider\Model\SearchResults\BannerSearchResults;
use Hryvinskyi\BannerSlider\Model\SearchResults\BreakpointSearchResults;
use Hryvinskyi\BannerSlider\Model\SearchResults\ResponsiveCropSearchResults;
use Hryvinskyi\BannerSlider\Model\SearchResults\SliderSearchResults;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractSearchResults::class)]
#[CoversClass(SliderSearchResults::class)]
#[CoversClass(BannerSearchResults::class)]
#[CoversClass(BreakpointSearchResults::class)]
#[CoversClass(ResponsiveCropSearchResults::class)]
class SearchResultsTest extends TestCase
{
    /**
     * A page keeps its items, criteria and total count
     *
     * @return void
     */
    public function testValues(): void
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $slider = $this->createMock(SliderInterface::class);
        $results = new SliderSearchResults($this->createMock(SearchCriteriaFactory::class));

        $results->setItems([$slider])->setSearchCriteria($criteria)->setTotalCount(12);

        self::assertSame([$slider], $results->getItems());
        self::assertSame($criteria, $results->getSearchCriteria());
        self::assertSame(12, $results->getTotalCount());
    }

    /**
     * A page never given criteria reports empty criteria and no matches
     *
     * @return void
     */
    public function testDefaults(): void
    {
        $empty = new SearchCriteria();
        $factory = $this->createMock(SearchCriteriaFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($empty);
        $results = new SliderSearchResults($factory);

        self::assertSame([], $results->getItems());
        self::assertSame(0, $results->getTotalCount());
        self::assertSame($empty, $results->getSearchCriteria());
        self::assertSame($empty, $results->getSearchCriteria());
    }

    /**
     * Every entity has its own typed page
     *
     * @return void
     */
    public function testTypedPages(): void
    {
        $factory = $this->createMock(SearchCriteriaFactory::class);
        $banner = $this->createMock(BannerInterface::class);
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $crop = $this->createMock(ResponsiveCropInterface::class);

        self::assertSame([$banner], (new BannerSearchResults($factory))->setItems([$banner])->getItems());
        self::assertSame(
            [$breakpoint],
            (new BreakpointSearchResults($factory))->setItems([$breakpoint])->getItems()
        );
        self::assertSame([$crop], (new ResponsiveCropSearchResults($factory))->setItems([$crop])->getItems());
    }
}
