<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\CommittedMediaRemover;
use Hryvinskyi\BannerSlider\Model\Media\ObsoleteMediaRemover;
use Hryvinskyi\BannerSlider\Model\ResourceModel\AfterCommitScheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommittedMediaRemover::class)]
class CommittedMediaRemoverTest extends TestCase
{
    /**
     * @var AfterCommitScheduler&MockObject
     */
    private MockObject $scheduler;

    /**
     * @var ObsoleteMediaRemover&MockObject
     */
    private MockObject $obsoleteMediaRemover;

    /**
     * Work handed to the scheduler, not yet run
     *
     * @var list<callable>
     */
    private array $scheduled = [];

    /**
     * Removals run: [paths, kept paths]
     *
     * @var list<array{0: array<mixed>, 1: array<mixed>}>
     */
    private array $removed = [];

    /**
     * @var CommittedMediaRemover
     */
    private CommittedMediaRemover $remover;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->scheduler = $this->createMock(AfterCommitScheduler::class);
        $this->scheduler->method('schedule')->willReturnCallback(function (callable $work): void {
            $this->scheduled[] = $work;
        });
        $this->obsoleteMediaRemover = $this->createMock(ObsoleteMediaRemover::class);
        $this->remover = new CommittedMediaRemover($this->scheduler, $this->obsoleteMediaRemover);
    }

    /**
     * The removal of the replaced files, keeping the written ones, runs only when the scheduler runs it
     *
     * @return void
     */
    public function testRemovalWaitsForTheScheduler(): void
    {
        $this->obsoleteMediaRemover->method('remove')->willReturnCallback(function (array $paths, array $keep): void {
            $this->removed[] = [$paths, $keep];
        });

        $this->remover->removeAfterCommit(['banner_slider/responsive/1/a.jpg'], ['banner_slider/responsive/1/b.jpg']);
        self::assertSame([], $this->removed);

        self::assertCount(1, $this->scheduled);
        foreach ($this->scheduled as $work) {
            $work();
        }
        self::assertSame(
            [[['banner_slider/responsive/1/a.jpg'], ['banner_slider/responsive/1/b.jpg']]],
            $this->removed
        );
    }

    /**
     * Nothing to remove schedules nothing
     *
     * @return void
     */
    public function testNoPathsDoNothing(): void
    {
        $this->scheduler->expects(self::never())->method('schedule');
        $this->obsoleteMediaRemover->expects(self::never())->method('remove');

        $this->remover->removeAfterCommit([]);
    }
}
