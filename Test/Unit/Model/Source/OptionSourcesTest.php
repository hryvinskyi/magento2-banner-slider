<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Source;

use Hryvinskyi\BannerSlider\Model\Source\AspectRatioOptions;
use Hryvinskyi\BannerSlider\Model\Source\BannerTypeOptions;
use Hryvinskyi\BannerSlider\Model\Source\SlideEffectOptions;
use Hryvinskyi\BannerSlider\Model\Source\SliderOptions;
use Hryvinskyi\BannerSlider\Model\Source\StatusOptions;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\SliderRepositoryInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusOptions::class)]
#[CoversClass(BannerTypeOptions::class)]
#[CoversClass(SlideEffectOptions::class)]
#[CoversClass(AspectRatioOptions::class)]
#[CoversClass(SliderOptions::class)]
class OptionSourcesTest extends TestCase
{
    /**
     * Enabled and disabled map to 1 and 0
     *
     * @return void
     */
    public function testStatusOptions(): void
    {
        self::assertSame(
            [[1, 'Enabled'], [0, 'Disabled']],
            $this->flatten((new StatusOptions())->toOptionArray())
        );
    }

    /**
     * Every banner type is offered with its label
     *
     * @return void
     */
    public function testBannerTypeOptions(): void
    {
        self::assertSame(
            [[0, 'Image'], [1, 'Video'], [2, 'Custom HTML']],
            $this->flatten((new BannerTypeOptions())->toOptionArray())
        );
    }

    /**
     * Every slide effect is offered with its label
     *
     * @return void
     */
    public function testSlideEffectOptions(): void
    {
        self::assertSame(
            [['slide', 'Slide'], ['fade', 'Fade']],
            $this->flatten((new SlideEffectOptions())->toOptionArray())
        );
    }

    /**
     * The aspect ratio presets, in display order
     *
     * @return void
     */
    public function testAspectRatioOptions(): void
    {
        self::assertSame(
            [['16:9', '16:9'], ['4:3', '4:3'], ['21:9', '21:9'], ['1:1', '1:1'], ['9:16', '9:16'], ['3:2', '3:2']],
            $this->flatten((new AspectRatioOptions())->toOptionArray())
        );
    }

    /**
     * Sliders are listed by name through the repository
     *
     * @return void
     */
    public function testSliderOptions(): void
    {
        $sortOrder = new SortOrder();
        $sortOrderBuilder = $this->createMock(SortOrderBuilder::class);
        $sortOrderBuilder->expects(self::once())->method('setField')->with('name')->willReturnSelf();
        $sortOrderBuilder->expects(self::once())->method('setDirection')->with(SortOrder::SORT_ASC)->willReturnSelf();
        $sortOrderBuilder->method('create')->willReturn($sortOrder);
        $criteria = new SearchCriteria();
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->expects(self::once())->method('addSortOrder')->with($sortOrder)->willReturnSelf();
        $criteriaBuilder->method('create')->willReturn($criteria);

        $home = $this->createMock(SliderInterface::class);
        $home->method('getSliderId')->willReturn(1);
        $home->method('getName')->willReturn('Home');
        $unsaved = $this->createMock(SliderInterface::class);
        $unsaved->method('getSliderId')->willReturn(null);
        $results = $this->createMock(SliderSearchResultsInterface::class);
        $results->method('getItems')->willReturn([$home, $unsaved]);
        $repository = $this->createMock(SliderRepositoryInterface::class);
        $repository->method('getList')->with($criteria)->willReturn($results);

        self::assertSame(
            [['value' => 1, 'label' => 'Home']],
            (new SliderOptions($repository, $criteriaBuilder, $sortOrderBuilder))->toOptionArray()
        );
    }

    /**
     * Options as [value, rendered label] pairs
     *
     * @param list<array{value: int|string, label: Phrase|string}> $options
     * @return list<array{0: int|string, 1: string}>
     */
    private function flatten(array $options): array
    {
        return array_map(
            fn (array $option): array => [
                $option['value'],
                $option['label'] instanceof Phrase ? $option['label']->render() : $option['label'],
            ],
            $options
        );
    }
}
