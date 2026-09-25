<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

use Magento\Customer\Api\Data\GroupInterface;

/**
 * Reads the comma-separated store and customer group ids that sliders stored before the link tables existed.
 *
 * Tokens are trimmed; anything that is not a plain non-negative integer is ignored, as are duplicates and ids that
 * no longer exist. An empty result never widens visibility: the legacy storefront filter matched nothing on an empty
 * value, so "nothing usable" stays "visible nowhere".
 */
class LegacyScopeParser
{
    /**
     * Store view id that stands for every store view
     */
    public const ALL_STORE_VIEWS = 0;

    /**
     * Parse legacy store view ids.
     *
     * Store view 0 already covers every store view, so when it is present the result is `[0]` alone.
     *
     * @param string|null $value Stored comma-separated ids
     * @param list<int> $existingStoreIds Ids present in the store table (0 included)
     * @return list<int> Existing ids, unique and ascending; empty when nothing usable was stored
     */
    public function parseStoreIds(?string $value, array $existingStoreIds): array
    {
        $ids = array_values(array_intersect($this->parseIds($value), $existingStoreIds));

        return in_array(self::ALL_STORE_VIEWS, $ids, true) ? [self::ALL_STORE_VIEWS] : $ids;
    }

    /**
     * Parse legacy customer group ids.
     *
     * The legacy "all groups" marker wins over any other id in the same value.
     *
     * @param string|null $value Stored comma-separated ids
     * @param list<int> $existingGroupIds Ids present in the customer group table
     * @return LegacyCustomerGroupScope
     */
    public function parseCustomerGroups(?string $value, array $existingGroupIds): LegacyCustomerGroupScope
    {
        $ids = $this->parseIds($value);
        if (in_array(GroupInterface::CUST_GROUP_ALL, $ids, true)) {
            return new LegacyCustomerGroupScope(true, []);
        }

        return new LegacyCustomerGroupScope(false, array_values(array_intersect($ids, $existingGroupIds)));
    }

    /**
     * Split a comma-separated value into unique non-negative integers in ascending order
     *
     * @param string|null $value
     * @return list<int>
     */
    private function parseIds(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $ids = [];
        foreach (explode(',', $value) as $token) {
            $token = trim($token);
            if ($token !== '' && ctype_digit($token)) {
                $ids[(int)$token] = (int)$token;
            }
        }
        sort($ids);

        return $ids;
    }
}
