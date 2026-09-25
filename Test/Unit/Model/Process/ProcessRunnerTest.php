<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Process;

use Hryvinskyi\BannerSlider\Model\Process\ProcessCreator;
use Hryvinskyi\BannerSlider\Model\Process\ProcessFailedException;
use Hryvinskyi\BannerSlider\Model\Process\ProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

#[CoversClass(ProcessRunner::class)]
#[CoversClass(ProcessCreator::class)]
class ProcessRunnerTest extends TestCase
{
    /**
     * @var Process&MockObject
     */
    private MockObject $process;

    /**
     * @var ProcessRunner
     */
    private ProcessRunner $runner;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->process = $this->createMock(Process::class);
        $creator = $this->createMock(ProcessCreator::class);
        $creator->method('create')->with(['/bin/tool', '--', '/tmp/in'], 30.0)->willReturn($this->process);
        $this->runner = new ProcessRunner($creator);
    }

    /**
     * A successful command completes quietly
     *
     * @return void
     */
    public function testSuccess(): void
    {
        $this->process->expects(self::once())->method('run')->willReturn(0);
        $this->process->method('isSuccessful')->willReturn(true);

        $this->runner->run(['/bin/tool', '--', '/tmp/in'], 30.0);
    }

    /**
     * A non-zero exit fails with the exit code and the error output
     *
     * @return void
     */
    public function testNonZeroExit(): void
    {
        $this->process->method('run')->willReturn(2);
        $this->process->method('isSuccessful')->willReturn(false);
        $this->process->method('getExitCode')->willReturn(2);
        $this->process->method('getErrorOutput')->willReturn("cannot read input\n");

        $this->expectException(ProcessFailedException::class);
        $this->expectExceptionMessage('The command "/bin/tool" exited with code 2: cannot read input');
        $this->runner->run(['/bin/tool', '--', '/tmp/in'], 30.0);
    }

    /**
     * A timeout, or a process that cannot start, fails with the cause kept
     *
     * @return void
     */
    public function testTimeout(): void
    {
        $timeout = new ProcessTimedOutException(new Process(['true']), ProcessTimedOutException::TYPE_GENERAL);
        $this->process->method('run')->willThrowException($timeout);

        try {
            $this->runner->run(['/bin/tool', '--', '/tmp/in'], 30.0);
            self::fail('The run should have failed.');
        } catch (ProcessFailedException $e) {
            self::assertSame($timeout, $e->getPrevious());
        }
    }

    /**
     * The creator builds a process for the exact command and timeout, without a shell
     *
     * @return void
     */
    public function testCreatorBuildsProcess(): void
    {
        $process = (new ProcessCreator())->create(['/bin/tool', '--', '/tmp/in file'], 12.5);

        self::assertSame("'/bin/tool' '--' '/tmp/in file'", $process->getCommandLine());
        self::assertSame(12.5, $process->getTimeout());
    }
}
