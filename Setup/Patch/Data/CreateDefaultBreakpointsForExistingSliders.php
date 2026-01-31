<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Breakpoint\DefaultBreakpointsCreator;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\CollectionFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Creates default breakpoints for existing sliders
 */
class CreateDefaultBreakpointsForExistingSliders implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CollectionFactory $sliderCollectionFactory
     * @param DefaultBreakpointsCreator $defaultBreakpointsCreator
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CollectionFactory $sliderCollectionFactory,
        private readonly DefaultBreakpointsCreator $defaultBreakpointsCreator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $collection = $this->sliderCollectionFactory->create();

        foreach ($collection as $slider) {
            $this->defaultBreakpointsCreator->createForSlider((int)$slider->getSliderId());
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
