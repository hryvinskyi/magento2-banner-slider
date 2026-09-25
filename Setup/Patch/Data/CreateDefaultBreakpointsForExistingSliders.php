<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSlider\Model\Slider\DefaultBreakpoints;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Gives every slider that has no breakpoint the default breakpoints.
 *
 * Works on the raw connection, so it does not depend on the models of the version it runs under. A slider that
 * already has any breakpoint is left alone.
 */
class CreateDefaultBreakpointsForExistingSliders implements DataPatchInterface
{
    private const SLIDER_TABLE = 'hryvinskyi_banner_slider';
    private const BREAKPOINT_TABLE = 'hryvinskyi_banner_slider_breakpoint';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param DefaultBreakpoints $defaultBreakpoints
     * @param TypedRowFetcher $rowFetcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly DefaultBreakpoints $defaultBreakpoints,
        private readonly TypedRowFetcher $rowFetcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $breakpointTable = $this->moduleDataSetup->getTable(self::BREAKPOINT_TABLE);
        $withBreakpoints = $connection->select()
            ->from(['breakpoint' => $breakpointTable], ['slider_id'])
            ->where('breakpoint.slider_id = slider.slider_id');
        $sliderIds = $this->rowFetcher->fetchIds(
            $connection,
            $connection->select()
                ->from(['slider' => $this->moduleDataSetup->getTable(self::SLIDER_TABLE)], ['slider_id'])
                ->where('NOT EXISTS (' . $withBreakpoints->assemble() . ')')
        );

        $rows = [];
        foreach ($sliderIds as $sliderId) {
            foreach ($this->defaultBreakpoints->get() as $breakpoint) {
                $rows[] = [
                    'slider_id' => $sliderId,
                    'name' => $breakpoint->getName(),
                    'identifier' => $breakpoint->getIdentifier(),
                    'media_query' => $breakpoint->getMediaQuery(),
                    'min_width' => $breakpoint->getMinWidth(),
                    'target_width' => $breakpoint->getTargetWidth(),
                    'target_height' => $breakpoint->getTargetHeight(),
                    'sort_order' => $breakpoint->getSortOrder(),
                    'status' => (int)$breakpoint->isEnabled(),
                ];
            }
        }
        if ($rows !== []) {
            $connection->insertMultiple($breakpointTable, $rows);
        }

        $this->logger->info(sprintf(
            'Banner slider migration: default breakpoints created for %d sliders.',
            count($sliderIds)
        ));

        return $this;
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
