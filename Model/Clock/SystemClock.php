<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * The system clock in UTC, the time zone every stored date of the slider tables uses.
 *
 * Bound to `Psr\Clock\ClockInterface` only in the constructor arguments of this package's own classes, so the rest
 * of the application keeps whatever clock it is configured with.
 */
class SystemClock implements ClockInterface
{
    /**
     * @inheritDoc
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
