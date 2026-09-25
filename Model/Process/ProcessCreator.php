<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Process;

use Symfony\Component\Process\Process;

/**
 * Creates external processes, so the classes that run them can be tested without starting one.
 */
class ProcessCreator
{
    /**
     * A process for the command, not started yet
     *
     * @param list<string> $command The binary followed by its arguments; no shell is involved
     * @param float $timeout Seconds before the process is stopped
     * @return Process
     */
    public function create(array $command, float $timeout): Process
    {
        return new Process($command, null, null, null, $timeout);
    }
}
