<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Process;

use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;

/**
 * Runs an external command to completion, without a shell.
 */
class ProcessRunner
{
    /**
     * @param ProcessCreator $processCreator
     */
    public function __construct(
        private readonly ProcessCreator $processCreator
    ) {
    }

    /**
     * Run the command and wait for it
     *
     * @param list<string> $command The binary followed by its arguments
     * @param float $timeout Seconds before the command is stopped
     * @return void
     * @throws ProcessFailedException When the command cannot start, times out or exits with a non-zero code
     */
    public function run(array $command, float $timeout): void
    {
        $process = $this->processCreator->create($command, $timeout);
        try {
            $process->run();
        } catch (ProcessException $e) {
            throw new ProcessFailedException(
                sprintf('The command "%s" did not complete: %s', $command[0] ?? '', $e->getMessage()),
                0,
                $e
            );
        }

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException(sprintf(
                'The command "%s" exited with code %d: %s',
                $command[0] ?? '',
                (int)$process->getExitCode(),
                trim($process->getErrorOutput())
            ));
        }
    }
}
