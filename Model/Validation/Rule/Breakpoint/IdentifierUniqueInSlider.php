<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule\Breakpoint;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\BreakpointRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;

/**
 * No two breakpoints of one slider share an identifier; crop file names are built from it.
 *
 * Reads the breakpoint collection rather than the breakpoint repository: the repository runs this rule, so depending
 * on it would make the object graph circular.
 */
class IdentifierUniqueInSlider implements BreakpointRuleInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(BreakpointInterface $breakpoint): array
    {
        $sliderId = $breakpoint->getSliderId();
        $identifier = $breakpoint->getIdentifier();
        if ($sliderId === null || $identifier === '') {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addSliderFilter($sliderId)->addIdentifierFilter($identifier);
        $breakpointId = $breakpoint->getBreakpointId();
        if ($breakpointId !== null) {
            $collection->addFieldToFilter(
                'main_table.' . BreakpointInterface::BREAKPOINT_ID,
                ['neq' => $breakpointId]
            );
        }

        if ($collection->getSize() === 0) {
            return [];
        }

        return [__('The slider already has a breakpoint with the identifier "%1".', $identifier)];
    }
}
