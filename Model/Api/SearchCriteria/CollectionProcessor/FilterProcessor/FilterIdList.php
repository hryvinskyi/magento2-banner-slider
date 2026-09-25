<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Api\SearchCriteria\CollectionProcessor\FilterProcessor;

use Magento\Framework\Api\Filter;

/**
 * Reads the ids a search criteria filter carries, and whether it keeps or excludes them.
 *
 * - Ids: one id, a comma-separated list, or an array of ids.
 *
 *   The value is read from the filter's data rather than through its string getter, because an `in` filter built in
 *   PHP carries an array. Values that are not whole numbers of zero or more are left out.
 * - Condition: `eq` (also when none is given) and `in` keep the ids, `neq` and `nin` exclude them. Any other
 *   condition cannot be answered by an id list and is refused, rather than silently read as `eq`.
 */
class FilterIdList
{
    private const KEEP_CONDITIONS = ['eq', 'in'];
    private const EXCLUDE_CONDITIONS = ['neq', 'nin'];

    /**
     * Ids of the filter value
     *
     * @param Filter $filter
     * @return list<int>
     */
    public function read(Filter $filter): array
    {
        $value = $filter->__toArray()[Filter::KEY_VALUE] ?? null;
        $candidates = is_array($value) ? $value : explode(',', is_scalar($value) ? (string)$value : '');

        $ids = [];
        foreach ($candidates as $candidate) {
            $candidate = is_scalar($candidate) ? trim((string)$candidate) : '';
            if ($candidate !== '' && ctype_digit($candidate)) {
                $ids[] = (int)$candidate;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether the filter excludes its ids rather than keeping them
     *
     * @param Filter $filter
     * @return bool
     * @throws \InvalidArgumentException When the condition is not one an id list can answer
     */
    public function excludes(Filter $filter): bool
    {
        $condition = strtolower((string)$filter->getConditionType());
        if (in_array($condition, self::KEEP_CONDITIONS, true)) {
            return false;
        }
        if (in_array($condition, self::EXCLUDE_CONDITIONS, true)) {
            return true;
        }

        throw new \InvalidArgumentException(sprintf(
            'The "%s" filter supports the conditions %s, got "%s".',
            $filter->getField(),
            implode(', ', [...self::KEEP_CONDITIONS, ...self::EXCLUDE_CONDITIONS]),
            $condition
        ));
    }
}
