<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Breakpoint;

use Hryvinskyi\BannerSlider\Model\BreakpointFactory;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates default breakpoints for a slider
 */
class DefaultBreakpointsCreator
{
    /**
     * Default breakpoint configurations
     */
    private const DEFAULT_BREAKPOINTS = [
        [
            'name' => 'Desktop',
            'identifier' => 'desktop',
            'media_query' => '(min-width: 1200px)',
            'min_width' => 1200,
            'target_width' => 1920,
            'target_height' => 600,
            'sort_order' => 10,
        ],
        [
            'name' => 'Tablet',
            'identifier' => 'tablet',
            'media_query' => '(min-width: 768px) and (max-width: 1199px)',
            'min_width' => 768,
            'target_width' => 992,
            'target_height' => 400,
            'sort_order' => 30,
        ],
        [
            'name' => 'Mobile',
            'identifier' => 'mobile',
            'media_query' => '(max-width: 767px)',
            'min_width' => 0,
            'target_width' => 767,
            'target_height' => 500,
            'sort_order' => 40,
        ],
    ];

    /**
     * @param BreakpointFactory $breakpointFactory
     * @param BreakpointRepositoryInterface $breakpointRepository
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly BreakpointFactory $breakpointFactory,
        private readonly BreakpointRepositoryInterface $breakpointRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create default breakpoints for a slider
     *
     * @param int $sliderId
     * @return BreakpointInterface[]
     */
    public function createForSlider(int $sliderId): array
    {
        if ($this->hasExistingBreakpoints($sliderId)) {
            return [];
        }

        $createdBreakpoints = [];

        foreach (self::DEFAULT_BREAKPOINTS as $breakpointData) {
            try {
                $breakpoint = $this->breakpointFactory->create();
                $breakpoint->setSliderId($sliderId);
                $breakpoint->setName($breakpointData['name']);
                $breakpoint->setIdentifier($breakpointData['identifier']);
                $breakpoint->setMediaQuery($breakpointData['media_query']);
                $breakpoint->setMinWidth($breakpointData['min_width']);
                $breakpoint->setTargetWidth($breakpointData['target_width']);
                $breakpoint->setTargetHeight($breakpointData['target_height']);
                $breakpoint->setSortOrder($breakpointData['sort_order']);
                $breakpoint->setStatus(1);

                $this->breakpointRepository->save($breakpoint);
                $createdBreakpoints[] = $breakpoint;
            } catch (\Exception $e) {
                $this->logger->error(
                    'Failed to create default breakpoint for slider',
                    [
                        'slider_id' => $sliderId,
                        'breakpoint' => $breakpointData['identifier'],
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        return $createdBreakpoints;
    }

    /**
     * Check if slider already has breakpoints
     *
     * @param int $sliderId
     * @return bool
     */
    private function hasExistingBreakpoints(int $sliderId): bool
    {
        $collection = $this->collectionFactory->create();
        $collection->addSliderFilter($sliderId);

        return $collection->getSize() > 0;
    }

    /**
     * Get default breakpoint configurations
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDefaultBreakpointConfigurations(): array
    {
        return self::DEFAULT_BREAKPOINTS;
    }
}
