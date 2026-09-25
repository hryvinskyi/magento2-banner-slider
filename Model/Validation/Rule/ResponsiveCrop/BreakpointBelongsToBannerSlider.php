<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCropRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * A crop is made for a breakpoint of its banner's own slider; a breakpoint of another slider never renders.
 */
class BreakpointBelongsToBannerSlider implements ResponsiveCropRuleInterface
{
    /**
     * @param BannerRepositoryInterface $bannerRepository
     * @param BreakpointRepositoryInterface $breakpointRepository
     */
    public function __construct(
        private readonly BannerRepositoryInterface $bannerRepository,
        private readonly BreakpointRepositoryInterface $breakpointRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(ResponsiveCropInterface $crop): array
    {
        $bannerId = $crop->getBannerId();
        $breakpointId = $crop->getBreakpointId();
        if ($bannerId === null || $breakpointId === null) {
            return [];
        }

        try {
            $bannerSliderId = $this->bannerRepository->getById($bannerId)->getSliderId();
        } catch (NoSuchEntityException) {
            return [__('The banner with id "%1" does not exist.', $bannerId)];
        }
        try {
            $breakpointSliderId = $this->breakpointRepository->getById($breakpointId)->getSliderId();
        } catch (NoSuchEntityException) {
            return [__('The breakpoint with id "%1" does not exist.', $breakpointId)];
        }

        if ($bannerSliderId === $breakpointSliderId) {
            return [];
        }

        return [__('Breakpoint %1 belongs to another slider than banner %2.', $breakpointId, $bannerId)];
    }
}
