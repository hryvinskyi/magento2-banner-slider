<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSlider\Model\Slider\DefaultBreakpoints;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\CreateDefaultBreakpointsForExistingSliders;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CreateDefaultBreakpointsForExistingSliders::class)]
class CreateDefaultBreakpointsForExistingSlidersTest extends TestCase
{
    /**
     * Conditions of the selects, in order
     *
     * @var list<string>
     */
    private array $conditions = [];

    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var CreateDefaultBreakpointsForExistingSliders
     */
    private CreateDefaultBreakpointsForExistingSliders $patch;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturnCallback(fn (): Select => $this->select());
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->willReturnArgument(0);

        $this->patch = new CreateDefaultBreakpointsForExistingSliders(
            $setup,
            new DefaultBreakpoints([
                'desktop' => [
                    'name' => 'Desktop',
                    'identifier' => 'desktop',
                    'media_query' => '(min-width: 1200px)',
                    'min_width' => '1200',
                    'target_width' => '1920',
                    'target_height' => '600',
                    'sort_order' => '10',
                ],
                'mobile' => [
                    'name' => 'Mobile',
                    'identifier' => 'mobile',
                    'media_query' => '(max-width: 767px)',
                    'min_width' => '0',
                    'target_width' => '767',
                    'sort_order' => '40',
                ],
            ]),
            new TypedRowFetcher(),
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * Sliders without any breakpoint get one row per default breakpoint
     *
     * @return void
     */
    public function testCreatesDefaultsForSlidersWithoutBreakpoints(): void
    {
        $this->connection->method('fetchCol')->willReturn(['4', '7']);
        $row = fn (int $sliderId, string $name, string $query, int $min, int $width, ?int $height, int $sort): array
            => [
                'slider_id' => $sliderId,
                'name' => $name,
                'identifier' => strtolower($name),
                'media_query' => $query,
                'min_width' => $min,
                'target_width' => $width,
                'target_height' => $height,
                'sort_order' => $sort,
                'status' => 1,
            ];
        $this->connection->expects(self::once())
            ->method('insertMultiple')
            ->with('hryvinskyi_banner_slider_breakpoint', [
                $row(4, 'Desktop', '(min-width: 1200px)', 1200, 1920, 600, 10),
                $row(4, 'Mobile', '(max-width: 767px)', 0, 767, null, 40),
                $row(7, 'Desktop', '(min-width: 1200px)', 1200, 1920, 600, 10),
                $row(7, 'Mobile', '(max-width: 767px)', 0, 767, null, 40),
            ]);

        self::assertSame($this->patch, $this->patch->apply());
        self::assertSame(
            ['breakpoint.slider_id = slider.slider_id', 'NOT EXISTS (SUBQUERY)'],
            $this->conditions
        );
    }

    /**
     * When every slider has breakpoints, nothing is written
     *
     * @return void
     */
    public function testNothingToCreate(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);
        $this->connection->expects(self::never())->method('insertMultiple');

        $this->patch->apply();
        self::assertSame([], CreateDefaultBreakpointsForExistingSliders::getDependencies());
        self::assertSame([], $this->patch->getAliases());
    }

    /**
     * A select double recording its conditions
     *
     * @return Select
     */
    private function select(): Select
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('assemble')->willReturn('SUBQUERY');
        $select->method('where')->willReturnCallback(function (string $condition) use ($select): Select {
            $this->conditions[] = $condition;

            return $select;
        });

        return $select;
    }
}
