<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Data\ResponsiveItemsCodec;
use Hryvinskyi\BannerSlider\Model\Migration\LegacyResponsiveItemsConverter;
use Hryvinskyi\BannerSlider\Model\Migration\MaxWidthBreakpointTranslator;
use Hryvinskyi\BannerSlider\Model\Migration\ResponsiveLayoutComparator;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\MigrateResponsiveItems;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\NonTransactionableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(MigrateResponsiveItems::class)]
class MigrateResponsiveItemsTest extends TestCase
{
    private const TABLE = 'hryvinskyi_banner_slider';

    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * Calls in order: transaction steps, updates and column drops
     *
     * @var list<string>
     */
    private array $calls = [];

    /**
     * Updates made: [table, data, where]
     *
     * @var list<array{0: string, 1: array<mixed>, 2: mixed}>
     */
    private array $updates = [];

    /**
     * Info messages logged, in order
     *
     * @var list<string>
     */
    private array $infos = [];

    /**
     * Warnings logged: [message, context]
     *
     * @var list<array{0: string, 1: array<mixed>}>
     */
    private array $warnings = [];

    /**
     * @var MigrateResponsiveItems
     */
    private MigrateResponsiveItems $patch;

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
        $this->connection->method('update')->willReturnCallback(
            function (string $table, array $data, mixed $where): int {
                $this->updates[] = [$table, $data, $where];
                $this->calls[] = 'update';

                return 1;
            }
        );
        $this->connection->method('dropColumn')->willReturnCallback(function (string $table, string $column): bool {
            $this->calls[] = 'drop ' . $column;

            return true;
        });
        foreach (['beginTransaction', 'commit', 'rollBack'] as $step) {
            $this->connection->method($step)->willReturnCallback(function () use ($step): AdapterInterface {
                $this->calls[] = $step;

                return $this->connection;
            });
        }
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->willReturnArgument(0);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->method('info')->willReturnCallback(function (string $message): void {
            $this->infos[] = $message;
        });
        $this->logger->method('warning')->willReturnCallback(function (string $message, array $context = []): void {
            $this->warnings[] = [$message, $context];
        });

        $this->patch = new MigrateResponsiveItems(
            $setup,
            new LegacyResponsiveItemsConverter(
                new ResponsiveItemsCodec(),
                new MaxWidthBreakpointTranslator(),
                new ResponsiveLayoutComparator()
            ),
            new TypedRowFetcher(),
            $this->logger
        );
    }

    /**
     * Dropping the flag column is not allowed inside the setup transaction, so the patch runs its own
     *
     * @return void
     */
    public function testIsNotTransactional(): void
    {
        self::assertTrue(
            (new \ReflectionClass(MigrateResponsiveItems::class))
                ->implementsInterface(NonTransactionableInterface::class)
        );
    }

    /**
     * Items of non-responsive sliders are removed, legacy values are converted, then the flag column is dropped
     *
     * @return void
     */
    public function testClearsNonResponsiveConvertsThenDropsTheFlag(): void
    {
        $this->connection->method('tableColumnExists')->with(self::TABLE, 'is_responsive')->willReturn(true);
        $this->connection->method('fetchCol')->willReturn(['4']);
        $this->connection->method('fetchPairs')->willReturn([
            '1' => '{"768":{"items":3},"0":{"items":1}}',
            '2' => '[{"min_width":0,"per_page":2,"gap":null}]',
            '3' => '{"0":{"items":1},"768":{"items":1},"1024":{"items":1}}',
        ]);

        $this->patch->apply();

        self::assertSame(
            ['beginTransaction', 'update', 'update', 'update', 'commit', 'drop is_responsive'],
            $this->calls
        );
        self::assertSame(
            [
                [self::TABLE, ['responsive_items' => null], ['slider_id IN (?)' => [4]]],
                [
                    self::TABLE,
                    [
                        'responsive_items' => '[{"min_width":0,"per_page":3,"gap":null},'
                            . '{"min_width":769,"per_page":1,"gap":null}]',
                    ],
                    ['slider_id = ?' => 1],
                ],
                [
                    self::TABLE,
                    ['responsive_items' => '[{"min_width":0,"per_page":1,"gap":null}]'],
                    ['slider_id = ?' => 3],
                ],
            ],
            $this->updates
        );
        self::assertCount(1, $this->warnings, 'Only the slider whose layout reading changed is flagged.');
        self::assertStringStartsWith('Banner slider migration: slider 1 responsive items', $this->warnings[0][0]);
        self::assertSame(
            'Banner slider migration: responsive items of 2 sliders converted to the list shape.',
            end($this->infos)
        );
    }

    /**
     * Once the flag column is gone, nothing is cleared or dropped
     *
     * @return void
     */
    public function testWithoutTheFlagColumnOnlyConverts(): void
    {
        $this->connection->method('tableColumnExists')->willReturn(false);
        $this->connection->expects(self::never())->method('fetchCol');
        $this->connection->method('fetchPairs')->willReturn(['2' => '[]']);

        $this->patch->apply();

        self::assertSame(['beginTransaction', 'commit'], $this->calls);
    }

    /**
     * A failure rolls the row changes back and keeps the flag column
     *
     * @return void
     */
    public function testFailureRollsBackAndKeepsTheFlag(): void
    {
        $this->connection->method('tableColumnExists')->willReturn(true);
        $this->connection->method('fetchCol')->willThrowException(new \RuntimeException('Lock wait timeout'));

        try {
            $this->patch->apply();
            self::fail('The failure must be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Lock wait timeout', $exception->getMessage());
        }
        self::assertSame(['beginTransaction', 'rollBack'], $this->calls);
    }

    /**
     * No dependencies and no aliases
     *
     * @return void
     */
    public function testHasNoDependencies(): void
    {
        self::assertSame([], MigrateResponsiveItems::getDependencies());
        self::assertSame([], $this->patch->getAliases());
    }
}
