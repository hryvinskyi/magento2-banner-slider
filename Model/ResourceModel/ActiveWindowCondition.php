<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Hryvinskyi\BannerSlider\Model\AbstractEntityModel;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\DB\Select;

/**
 * The SQL form of "the row's active window contains this moment", for tables with `from_date`/`to_date` columns.
 *
 * Both ends are inclusive and a NULL end is open, as in the active window value object. The moment is compared as a
 * UTC `Y-m-d H:i:s` string, the stored form; the caller supplies it, so no query reads the system clock itself.
 */
class ActiveWindowCondition
{
    /**
     * Restrict the select to rows active at the given moment
     *
     * @param Select $select
     * @param DateTimeInterface $at
     * @param string $tableAlias
     * @return void
     */
    public function apply(Select $select, DateTimeInterface $at, string $tableAlias = 'main_table'): void
    {
        $moment = DateTimeImmutable::createFromInterface($at)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(AbstractEntityModel::STORED_DATETIME_FORMAT);
        $from = $tableAlias . '.' . SliderInterface::FROM_DATE;
        $to = $tableAlias . '.' . SliderInterface::TO_DATE;

        $select->where(sprintf('(%1$s IS NULL OR %1$s <= ?)', $from), $moment);
        $select->where(sprintf('(%1$s IS NULL OR %1$s >= ?)', $to), $moment);
    }
}
