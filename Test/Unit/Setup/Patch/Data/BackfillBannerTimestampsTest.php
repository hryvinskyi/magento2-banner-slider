<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Setup\Patch\Data\BackfillBannerTimestamps;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(BackfillBannerTimestamps::class)]
class BackfillBannerTimestampsTest extends TestCase
{
    /**
     * Only empty timestamps are filled, with the database's own current time
     *
     * @return void
     */
    public function testFillsEmptyTimestampsWithDatabaseTime(): void
    {
        $updates = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('update')->willReturnCallback(
            function (string $table, array $data, mixed $where) use (&$updates): int {
                $value = $data[array_key_first($data)];
                $updates[] = [
                    $table,
                    array_key_first($data),
                    $value instanceof Expression ? (string)$value : $value,
                    $where,
                ];

                return 3;
            }
        );
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $patch = new BackfillBannerTimestamps($setup, $logger);

        self::assertSame($patch, $patch->apply());
        self::assertSame(
            [
                ['hryvinskyi_banner_slider_banner', 'created_at', 'CURRENT_TIMESTAMP', ['created_at IS NULL']],
                ['hryvinskyi_banner_slider_banner', 'updated_at', 'CURRENT_TIMESTAMP', ['updated_at IS NULL']],
            ],
            $updates
        );
        self::assertSame([], BackfillBannerTimestamps::getDependencies());
        self::assertSame([], $patch->getAliases());
    }
}
