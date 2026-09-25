<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Validation\Rule\Breakpoint;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\Breakpoint\IdentifierUniqueInSlider;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\Breakpoint\RequiredFields;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\Breakpoint\TargetSizeWithinLimit;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequiredFields::class)]
#[CoversClass(IdentifierUniqueInSlider::class)]
#[CoversClass(TargetSizeWithinLimit::class)]
class BreakpointRulesTest extends TestCase
{
    /**
     * A complete breakpoint passes
     *
     * @return void
     */
    public function testCompleteBreakpointPasses(): void
    {
        self::assertSame([], (new RequiredFields())->validate($this->breakpoint(2, 'Desktop', 'desktop', 'all', 1920)));
    }

    /**
     * Every missing field is reported
     *
     * @return void
     */
    public function testEveryMissingFieldIsReported(): void
    {
        self::assertCount(5, (new RequiredFields())->validate($this->breakpoint(null, '', '', ' ', 0)));
    }

    /**
     * An identifier used by another breakpoint of the slider is refused
     *
     * @return void
     */
    public function testDuplicateIdentifierIsRefused(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('addSliderFilter')->with(2)->willReturnSelf();
        $collection->expects(self::once())->method('addIdentifierFilter')->with('desktop')->willReturnSelf();
        $collection->expects(self::once())->method('addFieldToFilter')
            ->with('main_table.breakpoint_id', ['neq' => 7])->willReturnSelf();
        $collection->method('getSize')->willReturn(1);

        $errors = $this->rule($collection)->validate($this->breakpoint(2, 'Desktop', 'desktop', 'all', 1, 7));

        self::assertSame(['The slider already has a breakpoint with the identifier "desktop".'], [
            $errors[0]->render(),
        ]);
    }

    /**
     * A unique identifier passes; a new breakpoint is compared with every breakpoint of the slider
     *
     * @return void
     */
    public function testUniqueIdentifierPasses(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addSliderFilter')->willReturnSelf();
        $collection->method('addIdentifierFilter')->willReturnSelf();
        $collection->expects(self::never())->method('addFieldToFilter');
        $collection->method('getSize')->willReturn(0);

        self::assertSame([], $this->rule($collection)->validate($this->breakpoint(2, 'Mobile', 'mobile', 'all', 1)));
    }

    /**
     * Without a slider or an identifier there is nothing to compare
     *
     * @return void
     */
    public function testIncompleteBreakpointIsNotQueried(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');

        self::assertSame(
            [],
            (new IdentifierUniqueInSlider($factory))->validate($this->breakpoint(null, 'x', 'x', 'all', 1))
        );
    }

    /**
     * A stored target side above 5000 pixels is reported; up to 5000, and an open height, pass
     *
     * @param int $width
     * @param int|null $height
     * @param list<string> $expected
     * @return void
     */
    #[TestWith([5000, 5000, []])]
    #[TestWith([1920, null, []])]
    #[TestWith([5001, null, ['The target width of breakpoint "wide" must be at most 5000 pixels.']])]
    #[TestWith([100, 9000, ['The target height of breakpoint "wide" must be at most 5000 pixels.']])]
    public function testTargetSizeWithinLimit(int $width, ?int $height, array $expected): void
    {
        $breakpoint = $this->breakpoint(1, 'Wide', 'wide', 'all', $width);
        $breakpoint->method('getTargetHeight')->willReturn($height);

        self::assertSame($expected, array_map(
            static fn (Phrase $error): string => $error->render(),
            (new TargetSizeWithinLimit())->validate($breakpoint)
        ));
    }

    /**
     * The uniqueness rule over the collection
     *
     * @param Collection $collection
     * @return IdentifierUniqueInSlider
     */
    private function rule(Collection $collection): IdentifierUniqueInSlider
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new IdentifierUniqueInSlider($factory);
    }

    /**
     * A breakpoint double
     *
     * @param int|null $sliderId
     * @param string $name
     * @param string $identifier
     * @param string $mediaQuery
     * @param int $targetWidth
     * @param int|null $breakpointId
     * @return BreakpointInterface&MockObject
     */
    private function breakpoint(
        ?int $sliderId,
        string $name,
        string $identifier,
        string $mediaQuery,
        int $targetWidth,
        ?int $breakpointId = null
    ): BreakpointInterface&MockObject {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('getSliderId')->willReturn($sliderId);
        $breakpoint->method('getName')->willReturn($name);
        $breakpoint->method('getIdentifier')->willReturn($identifier);
        $breakpoint->method('getMediaQuery')->willReturn($mediaQuery);
        $breakpoint->method('getTargetWidth')->willReturn($targetWidth);
        $breakpoint->method('getBreakpointId')->willReturn($breakpointId);

        return $breakpoint;
    }
}
