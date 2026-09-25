<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Console\Command;

use Hryvinskyi\BannerSlider\Console\Command\CleanupMedia;
use Hryvinskyi\BannerSlider\Model\Media\OrphanMediaSweeper;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\FileSystemException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CleanupMedia::class)]
class CleanupMediaTest extends TestCase
{
    /**
     * @var OrphanMediaSweeper&MockObject
     */
    private MockObject $sweeper;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->sweeper = $this->createMock(OrphanMediaSweeper::class);
    }

    /**
     * Building the command and reading its definition call no service
     *
     * @return void
     */
    public function testConstructionIsCheap(): void
    {
        $this->sweeper->expects(self::never())->method(self::anything());

        $command = new CleanupMedia($this->sweeper);

        self::assertSame('banner-slider:media:cleanup', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    /**
     * A real run deletes and lists the deleted files
     *
     * @return void
     */
    public function testRun(): void
    {
        $this->sweeper->expects(self::once())->method('sweep')->with(false)
            ->willReturn(['banner_slider/image/a.jpg', 'banner_slider/video/b.mp4']);
        $tester = new CommandTester(new CleanupMedia($this->sweeper));

        self::assertSame(Cli::RETURN_SUCCESS, $tester->execute([]));
        self::assertSame(
            "banner_slider/image/a.jpg\nbanner_slider/video/b.mp4\n2 unreferenced file(s) deleted.\n",
            $tester->getDisplay()
        );
    }

    /**
     * A dry run only lists
     *
     * @return void
     */
    public function testDryRun(): void
    {
        $this->sweeper->expects(self::once())->method('sweep')->with(true)->willReturn([]);
        $tester = new CommandTester(new CleanupMedia($this->sweeper));

        self::assertSame(Cli::RETURN_SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertSame("0 unreferenced file(s) would be deleted.\n", $tester->getDisplay());
    }

    /**
     * A failure ends with an error code and message
     *
     * @return void
     */
    public function testFailure(): void
    {
        $this->sweeper->method('sweep')->willThrowException(new FileSystemException(__('The folder cannot be read.')));
        $tester = new CommandTester(new CleanupMedia($this->sweeper));

        self::assertSame(Cli::RETURN_FAILURE, $tester->execute([]));
        self::assertStringContainsString('The folder cannot be read.', $tester->getDisplay());
    }
}
