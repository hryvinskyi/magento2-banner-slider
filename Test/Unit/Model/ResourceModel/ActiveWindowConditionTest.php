<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel;

use DateTimeImmutable;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ActiveWindowCondition;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveWindowCondition::class)]
class ActiveWindowConditionTest extends TestCase
{
    /**
     * The moment is compared in UTC against both open-ended columns of the aliased table
     *
     * @return void
     */
    public function testApply(): void
    {
        $where = [];
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnCallback(
            function (string $condition, mixed $value) use (&$where, $select): Select {
                $where[] = [$condition, $value];

                return $select;
            }
        );

        (new ActiveWindowCondition())->apply($select, new DateTimeImmutable('2026-03-01T02:30:00+02:00'), 'b');

        self::assertSame(
            [
                ['(b.from_date IS NULL OR b.from_date <= ?)', '2026-03-01 00:30:00'],
                ['(b.to_date IS NULL OR b.to_date >= ?)', '2026-03-01 00:30:00'],
            ],
            $where
        );
    }
}
