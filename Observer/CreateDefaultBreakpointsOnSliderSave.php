<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Observer;

use Hryvinskyi\BannerSlider\Model\Breakpoint\DefaultBreakpointsCreator;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Creates default breakpoints when a new slider is saved
 */
class CreateDefaultBreakpointsOnSliderSave implements ObserverInterface
{
    /**
     * @param DefaultBreakpointsCreator $defaultBreakpointsCreator
     */
    public function __construct(
        private readonly DefaultBreakpointsCreator $defaultBreakpointsCreator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        /** @var SliderInterface $slider */
        $slider = $observer->getEvent()->getData('slider');

        if ($slider === null) {
            return;
        }

        $sliderId = $slider->getSliderId();
        if ($sliderId === null) {
            return;
        }

        $this->defaultBreakpointsCreator->createForSlider($sliderId);
    }
}
