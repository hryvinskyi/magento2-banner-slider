<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

/**
 * Runs a select on the raw connection and returns its cells as plain PHP types.
 *
 * Migrations read tables directly (models change between versions); this keeps their row handling typed instead of
 * casting untyped adapter results in every patch.
 */
class TypedRowFetcher
{
    /**
     * Integer values of the first column; non-numeric cells are skipped
     *
     * @param AdapterInterface $connection
     * @param Select $select
     * @return list<int>
     */
    public function fetchIds(AdapterInterface $connection, Select $select): array
    {
        $ids = [];
        foreach ($connection->fetchCol($select) as $value) {
            if (is_numeric($value)) {
                $ids[] = (int)$value;
            }
        }

        return $ids;
    }

    /**
     * First column as integer key, second column as nullable string value
     *
     * @param AdapterInterface $connection
     * @param Select $select
     * @return array<int, string|null>
     */
    public function fetchIdValuePairs(AdapterInterface $connection, Select $select): array
    {
        $pairs = [];
        foreach ($connection->fetchPairs($select) as $id => $value) {
            $pairs[(int)$id] = is_scalar($value) ? (string)$value : null;
        }

        return $pairs;
    }

    /**
     * Every row as column name => nullable string
     *
     * @param AdapterInterface $connection
     * @param Select $select
     * @return list<array<string, string|null>>
     */
    public function fetchRows(AdapterInterface $connection, Select $select): array
    {
        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $typed = [];
            foreach ($row as $column => $value) {
                $typed[(string)$column] = is_scalar($value) ? (string)$value : null;
            }
            $rows[] = $typed;
        }

        return $rows;
    }

    /**
     * The single value of a one-cell select as an integer (0 when it is not numeric)
     *
     * @param AdapterInterface $connection
     * @param Select $select
     * @return int
     */
    public function fetchInt(AdapterInterface $connection, Select $select): int
    {
        $value = $connection->fetchOne($select);

        return is_numeric($value) ? (int)$value : 0;
    }
}
