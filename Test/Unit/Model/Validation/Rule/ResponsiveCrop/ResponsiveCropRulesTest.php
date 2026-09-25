<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Validation\Rule\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCrop\BreakpointBelongsToBannerSlider;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCrop\RectRequiredWhenEnabled;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCrop\ReferencesAssigned;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferencesAssigned::class)]
#[CoversClass(RectRequiredWhenEnabled::class)]
#[CoversClass(BreakpointBelongsToBannerSlider::class)]
class ResponsiveCropRulesTest extends TestCase
{
    /**
     * A crop names its banner and breakpoint
     *
     * @return void
     */
    public function testReferencesAssigned(): void
    {
        self::assertSame([], (new ReferencesAssigned())->validate($this->crop(3, 4)));
        self::assertCount(2, (new ReferencesAssigned())->validate($this->crop(null, null)));
    }

    /**
     * An enabled crop needs a rectangle; a disabled one does not
     *
     * @param bool $enabled
     * @param bool $withRect
     * @param int $errors
     * @return void
     */
    #[TestWith([true, false, 1])]
    #[TestWith([true, true, 0])]
    #[TestWith([false, false, 0])]
    public function testRectRequiredWhenEnabled(bool $enabled, bool $withRect, int $errors): void
    {
        $crop = $this->crop(3, 4, $enabled, $withRect ? new CropRect(0, 0, 10, 10) : null);

        self::assertCount($errors, (new RectRequiredWhenEnabled())->validate($crop));
    }

    /**
     * The breakpoint must belong to the banner's slider
     *
     * @param int|null $breakpointSliderId
     * @param int $errors
     * @return void
     */
    #[TestWith([2, 0])]
    #[TestWith([5, 1])]
    public function testBreakpointBelongsToBannerSlider(?int $breakpointSliderId, int $errors): void
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getSliderId')->willReturn(2);
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('getSliderId')->willReturn($breakpointSliderId);
        $bannerRepository = $this->createMock(BannerRepositoryInterface::class);
        $bannerRepository->method('getById')->with(3)->willReturn($banner);
        $breakpointRepository = $this->createMock(BreakpointRepositoryInterface::class);
        $breakpointRepository->method('getById')->with(4)->willReturn($breakpoint);

        $rule = new BreakpointBelongsToBannerSlider($bannerRepository, $breakpointRepository);

        self::assertCount($errors, $rule->validate($this->crop(3, 4)));
    }

    /**
     * A missing banner or breakpoint is reported instead of thrown
     *
     * @return void
     */
    public function testMissingReferencesAreReported(): void
    {
        $bannerRepository = $this->createMock(BannerRepositoryInterface::class);
        $bannerRepository->method('getById')->willThrowException(new NoSuchEntityException(__('No banner.')));
        $breakpointRepository = $this->createMock(BreakpointRepositoryInterface::class);
        $breakpointRepository->expects(self::never())->method('getById');

        $errors = (new BreakpointBelongsToBannerSlider($bannerRepository, $breakpointRepository))
            ->validate($this->crop(3, 4));

        self::assertSame('The banner with id "3" does not exist.', $errors[0]->render());
    }

    /**
     * A crop without references is not looked up
     *
     * @return void
     */
    public function testUnassignedCropIsNotLookedUp(): void
    {
        $bannerRepository = $this->createMock(BannerRepositoryInterface::class);
        $bannerRepository->expects(self::never())->method('getById');

        self::assertSame(
            [],
            (new BreakpointBelongsToBannerSlider(
                $bannerRepository,
                $this->createMock(BreakpointRepositoryInterface::class)
            ))->validate($this->crop(null, 4))
        );
    }

    /**
     * A crop double
     *
     * @param int|null $bannerId
     * @param int|null $breakpointId
     * @param bool $enabled
     * @param CropRect|null $rect
     * @return ResponsiveCropInterface
     */
    private function crop(
        ?int $bannerId,
        ?int $breakpointId,
        bool $enabled = true,
        ?CropRect $rect = null
    ): ResponsiveCropInterface {
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->method('getBannerId')->willReturn($bannerId);
        $crop->method('getBreakpointId')->willReturn($breakpointId);
        $crop->method('isEnabled')->willReturn($enabled);
        $crop->method('getCropRect')->willReturn($rect);

        return $crop;
    }
}
