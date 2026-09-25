<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypedRowFetcher::class)]
class TypedRowFetcherTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var Select&MockObject
     */
    private MockObject $select;

    /**
     * @var TypedRowFetcher
     */
    private TypedRowFetcher $fetcher;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);
        $this->fetcher = new TypedRowFetcher();
    }

    /**
     * Numeric cells become integers; other cells are skipped
     *
     * @return void
     */
    public function testFetchIds(): void
    {
        $this->connection->method('fetchCol')->with($this->select)->willReturn(['3', 4, null, 'x', '0']);

        self::assertSame([3, 4, 0], $this->fetcher->fetchIds($this->connection, $this->select));
    }

    /**
     * Keys become integers and values nullable strings
     *
     * @return void
     */
    public function testFetchIdValuePairs(): void
    {
        $this->connection->method('fetchPairs')->with($this->select)->willReturn(['7' => '0,1', 8 => null, 9 => 12]);

        self::assertSame(
            [7 => '0,1', 8 => null, 9 => '12'],
            $this->fetcher->fetchIdValuePairs($this->connection, $this->select)
        );
    }

    /**
     * Rows keep their column names with nullable string values; non-row entries are skipped
     *
     * @return void
     */
    public function testFetchRows(): void
    {
        $this->connection->method('fetchAll')->with($this->select)->willReturn([
            ['crop_id' => 1, 'webp_image' => null, 'generate_webp' => true],
            'not a row',
        ]);

        self::assertSame(
            [['crop_id' => '1', 'webp_image' => null, 'generate_webp' => '1']],
            $this->fetcher->fetchRows($this->connection, $this->select)
        );
    }

    /**
     * A single cell is read as an integer, anything non-numeric as 0
     *
     * @param mixed $cell
     * @param int $expected
     * @return void
     */
    #[TestWith(['5', 5])]
    #[TestWith([2, 2])]
    #[TestWith([false, 0])]
    #[TestWith([null, 0])]
    public function testFetchInt(mixed $cell, int $expected): void
    {
        $this->connection->method('fetchOne')->with($this->select)->willReturn($cell);

        self::assertSame($expected, $this->fetcher->fetchInt($this->connection, $this->select));
    }
}
