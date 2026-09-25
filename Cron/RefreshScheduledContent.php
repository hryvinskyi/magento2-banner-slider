<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Cron;

use Hryvinskyi\BannerSlider\Model\Cache\ScheduleBoundaryRefresher;

/**
 * Every five minutes: cleans the cached pages of sliders and banners whose active window opened or closed since the
 * previous run, so they appear and disappear on time instead of at the next unrelated cache purge.
 */
class RefreshScheduledContent
{
    /**
     * @param ScheduleBoundaryRefresher $refresher
     */
    public function __construct(
        private readonly ScheduleBoundaryRefresher $refresher
    ) {
    }

    /**
     * Run the refresh
     *
     * @return void
     */
    public function execute(): void
    {
        $this->refresher->refresh();
    }
}
