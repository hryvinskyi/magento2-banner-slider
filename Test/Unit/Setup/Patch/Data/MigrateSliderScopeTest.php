<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyScopeParser;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\MigrateSliderScope;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(MigrateSliderScope::class)]
class MigrateSliderScopeTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * Calls made on the connection, in order
     *
     * @var list<array{0: string, 1: string, 2: mixed}>
     */
    private array $calls = [];

    /**
     * @var MigrateSliderScope
     */
    private MigrateSliderScope $patch;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('beginTransaction')->willReturnCallback(
            fn () => $this->record('begin', '', null)
        );
        $this->connection->method('commit')->willReturnCallback(fn () => $this->record('commit', '', null));
        $this->connection->method('rollBack')->willReturnCallback(fn () => $this->record('rollback', '', null));
        $this->connection->method('update')->willReturnCallback(
            fn (string $table, array $bind, array $where) => $this->record('update', $table, [$bind, $where])
        );
        $this->connection->method('dropColumn')->willReturnCallback(
            fn (string $table, string $column) => $this->record('drop', $table, $column)
        );

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->willReturnArgument(0);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->patch = new MigrateSliderScope($setup, new LegacyScopeParser(), new TypedRowFetcher(), $this->logger);
    }

    /**
     * Link rows and flags are written and committed before the legacy columns are dropped
     *
     * @return void
     */
    public function testCopiesThenCommitsThenDropsColumns(): void
    {
        $this->connection->method('tableColumnExists')->willReturn(true);
        $this->connection->method('fetchPairs')->willReturnOnConsecutiveCalls(
            [1 => '0', 2 => '3, 4', 3 => '', 4 => null],
            [1 => '32000', 2 => '2,1', 3 => '99', 4 => null]
        );
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(['0', '3', '4'], ['0', '1', '2']);
        $this->connection->method('insertOnDuplicate')->willReturnCallback(
            fn (string $table, array $data) => $this->record('insert', $table, $data)
        );
        $this->logger->expects(self::exactly(2))->method('warning');

        $this->patch->apply();

        self::assertSame(
            [
                ['begin', '', null],
                ['insert', 'hryvinskyi_banner_slider_store', [
                    ['slider_id' => 1, 'store_id' => 0],
                    ['slider_id' => 2, 'store_id' => 3],
                    ['slider_id' => 2, 'store_id' => 4],
                ]],
                ['update', 'hryvinskyi_banner_slider', [['all_customer_groups' => 1], ['slider_id IN (?)' => [1]]]],
                ['update', 'hryvinskyi_banner_slider', [
                    ['all_customer_groups' => 0],
                    ['slider_id IN (?)' => [2, 3, 4]],
                ]],
                ['insert', 'hryvinskyi_banner_slider_customer_group', [
                    ['slider_id' => 2, 'customer_group_id' => 1],
                    ['slider_id' => 2, 'customer_group_id' => 2],
                ]],
                ['commit', '', null],
                ['drop', 'hryvinskyi_banner_slider', 'store_ids'],
                ['drop', 'hryvinskyi_banner_slider', 'customer_group_ids'],
            ],
            $this->calls
        );
    }

    /**
     * A failed copy rolls back and leaves the legacy columns in place
     *
     * @return void
     */
    public function testFailedCopyRollsBackAndKeepsColumns(): void
    {
        $this->connection->method('tableColumnExists')->willReturn(true);
        $this->connection->method('fetchPairs')->willReturn([1 => '0']);
        $this->connection->method('fetchCol')->willReturn(['0']);
        $this->connection->method('insertOnDuplicate')->willThrowException(new \RuntimeException('write failed'));

        try {
            $this->patch->apply();
            self::fail('The write failure must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('write failed', $exception->getMessage());
        }

        self::assertSame([['begin', '', null], ['rollback', '', null]], $this->calls);
    }

    /**
     * Without the legacy columns nothing is read, written or dropped
     *
     * @return void
     */
    public function testDoesNothingWithoutLegacyColumns(): void
    {
        $this->connection->method('tableColumnExists')->willReturn(false);
        $this->connection->expects(self::never())->method('fetchPairs');

        $this->patch->apply();

        self::assertSame([], $this->calls);
    }

    /**
     * Only a remaining legacy column is migrated and dropped
     *
     * @return void
     */
    public function testMigratesOnlyTheRemainingColumn(): void
    {
        $this->connection->method('tableColumnExists')->willReturnCallback(
            fn (string $table, string $column): bool => $column === 'customer_group_ids'
        );
        $this->connection->method('fetchPairs')->willReturn([5 => '32000']);
        $this->connection->method('fetchCol')->willReturn(['0', '1']);
        $this->connection->method('insertOnDuplicate')->willReturnCallback(
            fn (string $table, array $data) => $this->record('insert', $table, $data)
        );

        $this->patch->apply();

        self::assertSame(
            [
                ['begin', '', null],
                ['update', 'hryvinskyi_banner_slider', [['all_customer_groups' => 1], ['slider_id IN (?)' => [5]]]],
                ['commit', '', null],
                ['drop', 'hryvinskyi_banner_slider', 'customer_group_ids'],
            ],
            $this->calls
        );
    }

    /**
     * Record a connection call
     *
     * @param string $operation
     * @param string $table
     * @param mixed $payload
     * @return int
     */
    private function record(string $operation, string $table, mixed $payload): int
    {
        $this->calls[] = [$operation, $table, $payload];

        return 1;
    }
}
