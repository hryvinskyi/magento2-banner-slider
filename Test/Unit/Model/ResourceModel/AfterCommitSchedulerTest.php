<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel;

use Hryvinskyi\BannerSlider\Model\ResourceModel\AfterCommitScheduler;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AfterCommitScheduler::class)]
class AfterCommitSchedulerTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var ResponsiveCropResource&MockObject
     */
    private MockObject $resource;

    /**
     * Commit callbacks registered on the resource
     *
     * @var list<mixed>
     */
    private array $callbacks = [];

    /**
     * Number of times the work ran
     *
     * @var int
     */
    private int $runs = 0;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->resource = $this->createMock(ResponsiveCropResource::class);
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('addCommitCallback')->willReturnCallback(
            function (mixed $callback): ResponsiveCropResource {
                $this->callbacks[] = $callback;

                return $this->resource;
            }
        );
    }

    /**
     * Outside a transaction the work runs at once
     *
     * @return void
     */
    public function testRunsAtOnceOutsideATransaction(): void
    {
        $this->connection->method('getTransactionLevel')->willReturn(0);

        (new AfterCommitScheduler($this->resource))->schedule(function (): void {
            $this->runs++;
        });

        self::assertSame(1, $this->runs);
        self::assertSame([], $this->callbacks);
    }

    /**
     * Inside a transaction, at any depth, the work waits in the commit callbacks of the rows' connection
     *
     * @param int $level
     * @return void
     */
    #[TestWith([1])]
    #[TestWith([3])]
    public function testWaitsForTheCommitInsideATransaction(int $level): void
    {
        $this->connection->method('getTransactionLevel')->willReturn($level);

        (new AfterCommitScheduler($this->resource))->schedule(function (): void {
            $this->runs++;
        });

        self::assertSame(0, $this->runs);
        self::assertCount(1, $this->callbacks);
        foreach ($this->callbacks as $callback) {
            self::assertIsCallable($callback);
            $callback();
        }
        self::assertSame(1, $this->runs);
    }
}
