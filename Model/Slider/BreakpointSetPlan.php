<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider;

use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;

/**
 * What saving a slider's desired breakpoints changes, already checked: the breakpoints to save (existing ones with
 * the desired values applied, and new ones), the existing ones to delete, the existing ones whose identifier
 * changes, and the existing ones whose target size changes.
 */
class BreakpointSetPlan
{
    /**
     * @param list<BreakpointInterface> $toSave In the order they were given
     * @param list<BreakpointInterface> $toDelete
     * @param list<BreakpointInterface> $renamed Existing breakpoints among `$toSave` whose identifier changes
     * @param list<BreakpointInterface> $retargeted Existing breakpoints among `$toSave` whose target width or height
     *     changes
     */
    public function __construct(
        private readonly array $toSave,
        private readonly array $toDelete,
        private readonly array $renamed,
        private readonly array $retargeted = []
    ) {
    }

    /**
     * Breakpoints to save, with the desired values applied
     *
     * @return list<BreakpointInterface>
     */
    public function getToSave(): array
    {
        return $this->toSave;
    }

    /**
     * Existing breakpoints the desired set no longer contains
     *
     * @return list<BreakpointInterface>
     */
    public function getToDelete(): array
    {
        return $this->toDelete;
    }

    /**
     * Existing breakpoints whose identifier changes; they already carry the new identifier
     *
     * @return list<BreakpointInterface>
     */
    public function getRenamed(): array
    {
        return $this->renamed;
    }

    /**
     * Existing breakpoints whose target width or height changes; they already carry the new target
     *
     * Their crops were cut for the previous target, so their areas and files have to follow the new one.
     *
     * @return list<BreakpointInterface>
     */
    public function getRetargeted(): array
    {
        return $this->retargeted;
    }

    /**
     * Ids of the breakpoints to delete
     *
     * @return list<int>
     */
    public function getIdsToDelete(): array
    {
        $ids = [];
        foreach ($this->toDelete as $breakpoint) {
            $id = $breakpoint->getBreakpointId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
