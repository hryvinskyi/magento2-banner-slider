<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Process;

/**
 * An external command could not be started, timed out or exited with an error.
 *
 * The message carries the command's exit code and error output. It is meant for the log, never for an admin page.
 */
class ProcessFailedException extends \RuntimeException
{
}
