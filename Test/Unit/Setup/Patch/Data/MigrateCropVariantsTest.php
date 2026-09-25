<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\MigrateCropVariants;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(MigrateCropVariants::class)]
class MigrateCropVariantsTest extends TestCase
{
    private const LEGACY_COLUMNS = [
        'generate_webp',
        'webp_image',
        'webp_quality',
        'generate_avif',
        'avif_image',
        'avif_quality',
    ];

    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * Calls made on the connection, in order
     *
     * @var list<array{0: string, 1: mixed}>
     */
    private array $calls = [];

    /**
     * @var MigrateCropVariants
     */
    private MigrateCropVariants $patch;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('beginTransaction')->willReturnCallback(fn () => $this->record('begin', null));
        $this->connection->method('commit')->willReturnCallback(fn () => $this->record('commit', null));
        $this->connection->method('rollBack')->willReturnCallback(fn () => $this->record('rollback', null));
        $this->connection->method('dropColumn')->willReturnCallback(
            fn (string $table, string $column) => $this->record('drop', $column)
        );

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->willReturnArgument(0);

        $this->patch = new MigrateCropVariants(
            $setup,
            new TypedRowFetcher(),
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * Requested or generated formats become variants, committed before the legacy columns are dropped
     *
     * @return void
     */
    public function testCopiesThenCommitsThenDropsColumns(): void
    {
        $this->connection->method('tableColumnExists')->willReturn(true);
        $this->connection->method('fetchAll')->willReturn([
            [
                'crop_id' => '1',
                'generate_webp' => '1',
                'webp_image' => null,
                'webp_quality' => '85',
                'generate_avif' => '0',
                'avif_image' => null,
                'avif_quality' => '80',
            ],
            [
                'crop_id' => '2',
                'generate_webp' => '0',
                'webp_image' => 'banner_slider/responsive/2/d.webp',
                'webp_quality' => '0',
                'generate_avif' => '1',
                'avif_image' => '',
                'avif_quality' => '250',
            ],
            [
                'crop_id' => '3',
                'generate_webp' => '0',
                'webp_image' => ' ',
                'webp_quality' => '85',
                'generate_avif' => '0',
                'avif_image' => null,
                'avif_quality' => '80',
            ],
        ]);
        $this->connection->method('insertOnDuplicate')->willReturnCallback(
            fn (string $table, array $data, array $fields) => $this->record('insert', [$table, $data, $fields])
        );

        $this->patch->apply();

        $expected = [
            ['begin', null],
            ['insert', [
                'hryvinskyi_banner_slider_crop_variant',
                [
                    ['crop_id' => 1, 'format' => 'webp', 'quality' => 85, 'path' => null],
                    ['crop_id' => 2, 'format' => 'webp', 'quality' => 1, 'path' => 'banner_slider/responsive/2/d.webp'],
                    ['crop_id' => 2, 'format' => 'avif', 'quality' => 100, 'path' => null],
                ],
                ['quality', 'path'],
            ]],
            ['commit', null],
        ];
        foreach (self::LEGACY_COLUMNS as $column) {
            $expected[] = ['drop', $column];
        }
        self::assertSame($expected, $this->calls);
    }

    /**
     * A missing quality column falls back to the legacy default quality of the format
     *
     * @return void
     */
    public function testMissingQualityColumnUsesLegacyDefault(): void
    {
        $this->connection->method('tableColumnExists')->willReturnCallback(
            fn (string $table, string $column): bool => in_array($column, ['generate_avif', 'avif_image'], true)
        );
        $this->connection->method('fetchAll')->willReturn([
            ['crop_id' => '4', 'generate_avif' => '1', 'avif_image' => null],
        ]);
        $this->connection->method('insertOnDuplicate')->willReturnCallback(
            fn (string $table, array $data, array $fields) => $this->record('insert', $data)
        );

        $this->patch->apply();

        self::assertSame(
            [
                ['begin', null],
                ['insert', [['crop_id' => 4, 'format' => 'avif', 'quality' => 80, 'path' => null]]],
                ['commit', null],
                ['drop', 'generate_avif'],
                ['drop', 'avif_image'],
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
        $this->connection->method('fetchAll')->willReturn([
            ['crop_id' => '1', 'generate_webp' => '1', 'webp_image' => null, 'webp_quality' => '85'],
        ]);
        $this->connection->method('insertOnDuplicate')->willThrowException(new \RuntimeException('write failed'));

        try {
            $this->patch->apply();
            self::fail('The write failure must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('write failed', $exception->getMessage());
        }

        self::assertSame([['begin', null], ['rollback', null]], $this->calls);
    }

    /**
     * Without the legacy columns nothing is read, written or dropped
     *
     * @return void
     */
    public function testDoesNothingWithoutLegacyColumns(): void
    {
        $this->connection->method('tableColumnExists')->willReturn(false);
        $this->connection->expects(self::never())->method('fetchAll');

        $this->patch->apply();

        self::assertSame([], $this->calls);
    }

    /**
     * Record a connection call
     *
     * @param string $operation
     * @param mixed $payload
     * @return int
     */
    private function record(string $operation, mixed $payload): int
    {
        $this->calls[] = [$operation, $payload];

        return 1;
    }
}
