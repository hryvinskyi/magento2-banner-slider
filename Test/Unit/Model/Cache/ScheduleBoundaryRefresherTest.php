<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Cache;

use DateTimeImmutable;
use DateTimeZone;
use Hryvinskyi\BannerSlider\Model\Cache\ScheduleBoundaryRefresher;
use Hryvinskyi\BannerSlider\Model\Cache\TagCleaner;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\FlagManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(ScheduleBoundaryRefresher::class)]
class ScheduleBoundaryRefresherTest extends TestCase
{
    private const NOW_UTC = '2026-09-25 10:05:00';

    /**
     * Tables read, in order
     *
     * @var list<string>
     */
    private array $tables = [];

    /**
     * Conditions of the selects, in order
     *
     * @var list<string>
     */
    private array $conditions = [];

    /**
     * Rows each query returns, in order
     *
     * @var list<list<array<string, mixed>>>
     */
    private array $results = [];

    /**
     * @var FlagManager&MockObject
     */
    private MockObject $flagManager;

    /**
     * @var TagCleaner&MockObject
     */
    private MockObject $tagCleaner;

    /**
     * @var ScheduleBoundaryRefresher
     */
    private ScheduleBoundaryRefresher $refresher;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            fn (string $text, mixed $value): string => str_replace(
                '?',
                "'" . (is_scalar($value) ? (string)$value : '') . "'",
                $text
            )
        );
        $connection->method('select')->willReturnCallback(fn (): Select => $this->select());
        $connection->method('fetchAll')->willReturnCallback(fn (): array => array_shift($this->results) ?? []);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(
            new DateTimeImmutable('2026-09-25 13:05:00', new DateTimeZone('Europe/Kyiv'))
        );
        $this->flagManager = $this->createMock(FlagManager::class);
        $this->tagCleaner = $this->createMock(TagCleaner::class);

        $this->refresher = new ScheduleBoundaryRefresher(
            $resourceConnection,
            $this->flagManager,
            $clock,
            $this->tagCleaner
        );
    }

    /**
     * Without a previous run nothing is compared or cleaned; the run time is stored in UTC
     *
     * @param mixed $stored
     * @return void
     */
    #[TestWith([null])]
    #[TestWith(['not a date'])]
    #[TestWith([false])]
    public function testFirstRunOnlyStoresTheTime(mixed $stored): void
    {
        $this->flagManager->method('getFlagData')->with(ScheduleBoundaryRefresher::FLAG_CODE)->willReturn($stored);
        $this->flagManager->expects(self::once())
            ->method('saveFlag')
            ->with(ScheduleBoundaryRefresher::FLAG_CODE, self::NOW_UTC);
        $this->tagCleaner->expects(self::never())->method('clean');

        self::assertSame([], $this->refresher->refresh());
        self::assertSame([], $this->tables);
    }

    /**
     * Sliders and banners whose window opened or closed since the previous run get their pages cleaned
     *
     * @return void
     */
    public function testCleansTagsOfRowsThatCrossedABoundary(): void
    {
        $this->flagManager->method('getFlagData')->willReturn('2026-09-25 10:00:00');
        $this->results = [
            [
                ['slider_id' => '3', 'location' => 'Home-Top'],
                ['slider_id' => '4', 'location' => null],
                ['slider_id' => '5', 'location' => 'not valid!'],
            ],
            [
                ['banner_id' => '12', 'slider_id' => '3'],
                ['banner_id' => '13', 'slider_id' => '6'],
            ],
        ];
        $expected = [
            'hryvinskyi_banner_slider_3',
            'hryvinskyi_banner_slider_location_home_top',
            'hryvinskyi_banner_slider_4',
            'hryvinskyi_banner_slider_5',
            'hryvinskyi_banner_slider_banner_12',
            'hryvinskyi_banner_slider_banner_13',
            'hryvinskyi_banner_slider_6',
        ];
        $this->tagCleaner->expects(self::once())->method('clean')->with($expected);
        $this->flagManager->expects(self::once())
            ->method('saveFlag')
            ->with(ScheduleBoundaryRefresher::FLAG_CODE, self::NOW_UTC);

        self::assertSame($expected, $this->refresher->refresh());
        self::assertSame(['hryvinskyi_banner_slider', 'hryvinskyi_banner_slider_banner'], $this->tables);
        $window = "(from_date > '2026-09-25 10:00:00' AND from_date <= '2026-09-25 10:05:00')"
            . " OR (to_date >= '2026-09-25 10:00:00' AND to_date < '2026-09-25 10:05:00')";
        self::assertSame([$window, $window], $this->conditions);
    }

    /**
     * A previous run that is not earlier than now (a clock set back) cleans nothing but moves the flag
     *
     * @return void
     */
    public function testClockSetBackCleansNothing(): void
    {
        $this->flagManager->method('getFlagData')->willReturn('2026-09-25 11:00:00');
        $this->tagCleaner->expects(self::never())->method('clean');
        $this->flagManager->expects(self::once())->method('saveFlag')->with(self::anything(), self::NOW_UTC);

        self::assertSame([], $this->refresher->refresh());
    }

    /**
     * A select double recording the table and condition it gets
     *
     * @return Select
     */
    private function select(): Select
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnCallback(function (mixed $table) use ($select): Select {
            $this->tables[] = is_string($table) ? $table : '';

            return $select;
        });
        $select->method('where')->willReturnCallback(function (string $condition) use ($select): Select {
            $this->conditions[] = $condition;

            return $select;
        });

        return $select;
    }
}
