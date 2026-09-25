<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

/**
 * Customer group scope read from a legacy comma-separated `customer_group_ids` value.
 *
 * Exactly one of three states:
 * - all groups: the legacy value held the "all groups" marker; no group ids;
 * - these groups: a non-empty list of existing group ids;
 * - none: nothing usable was stored, so the legacy storefront showed the slider to nobody.
 */
class LegacyCustomerGroupScope
{
    /**
     * @param bool $allGroups Whether the legacy value meant every customer group
     * @param list<int> $groupIds Existing group ids, unique and ascending; empty when $allGroups is true
     * @throws \InvalidArgumentException When both "all groups" and a group list are given
     */
    public function __construct(
        private readonly bool $allGroups,
        private readonly array $groupIds
    ) {
        if ($allGroups && $groupIds !== []) {
            throw new \InvalidArgumentException('A scope for all customer groups cannot also list group ids.');
        }
    }

    /**
     * Whether the slider is meant for every customer group
     *
     * @return bool
     */
    public function isAllGroups(): bool
    {
        return $this->allGroups;
    }

    /**
     * The listed customer group ids, unique and ascending
     *
     * @return list<int>
     */
    public function getGroupIds(): array
    {
        return $this->groupIds;
    }

    /**
     * Whether the slider is meant for nobody: not all groups and no listed group
     *
     * @return bool
     */
    public function isNone(): bool
    {
        return !$this->allGroups && $this->groupIds === [];
    }
}
