<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyLocationNormaliser;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\NormaliseLegacyLocations;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\NormaliseLegacyRows;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(NormaliseLegacyLocations::class)]
class NormaliseLegacyLocationsTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * Updates made: [table, data, where]
     *
     * @var list<array{0: string, 1: array<mixed>, 2: mixed}>
     */
    private array $updates = [];

    /**
     * Warnings logged: [message, context]
     *
     * @var list<array{0: string, 1: array<mixed>}>
     */
    private array $warnings = [];

    /**
     * Info messages logged
     *
     * @var list<string>
     */
    private array $infos = [];

    /**
     * @var NormaliseLegacyLocations
     */
    private NormaliseLegacyLocations $patch;

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

                return 1;
            }
        );
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->willReturnArgument(0);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function (string $message, array $context = []): void {
            $this->warnings[] = [$message, $context];
        });
        $logger->method('info')->willReturnCallback(function (string $message): void {
            $this->infos[] = $message;
        });

        $this->patch = new NormaliseLegacyLocations(
            $setup,
            new LegacyLocationNormaliser(),
            new TypedRowFetcher(),
            $logger
        );
    }

    /**
     * Invalid locations are rewritten and logged old → new; valid ones are left alone; collisions are allowed
     *
     * @return void
     */
    public function testRewritesInvalidLocations(): void
    {
        $this->connection->method('fetchPairs')->willReturn([
            '1' => 'homepage_slider_main',
            '2' => 'home page',
            '3' => 'home_page',
            '4' => '  ',
        ]);

        $this->patch->apply();

        self::assertSame(
            [
                ['hryvinskyi_banner_slider', ['location' => 'home_page'], ['slider_id = ?' => 2]],
                ['hryvinskyi_banner_slider', ['location' => null], ['slider_id = ?' => 4]],
            ],
            $this->updates
        );
        self::assertSame(
            [
                'Banner slider migration: slider 2 location "home page" → "home_page"; change layouts and widgets '
                . 'that place a slider at the old location.',
                ['slider_id' => 2, 'old_location' => 'home page', 'new_location' => 'home_page'],
            ],
            $this->warnings[0]
        );
        self::assertCount(2, $this->warnings);
        self::assertSame(
            ['Banner slider migration: 2 slider locations rewritten as valid location codes.'],
            $this->infos
        );
    }

    /**
     * It runs after the legacy rows are normalised, and has no aliases
     *
     * @return void
     */
    public function testRunsAfterRowNormalisation(): void
    {
        self::assertSame([NormaliseLegacyRows::class], NormaliseLegacyLocations::getDependencies());
        self::assertSame([], $this->patch->getAliases());
    }
}
