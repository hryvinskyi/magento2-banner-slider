<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Cron;

use Hryvinskyi\BannerSlider\Model\Config\MediaConfig;
use Hryvinskyi\BannerSlider\Model\Media\OrphanMediaSweeper;
use Magento\Framework\Exception\LocalizedException;

/**
 * Daily: deletes banner slider media files nothing references, only when an admin has switched the sweep on.
 *
 * The sweep deletes files, so it ships off; while it is off the job does nothing and logs nothing.
 */
class SweepOrphanMedia
{
    /**
     * @param MediaConfig $mediaConfig
     * @param OrphanMediaSweeper $sweeper
     */
    public function __construct(
        private readonly MediaConfig $mediaConfig,
        private readonly OrphanMediaSweeper $sweeper
    ) {
    }

    /**
     * Run the sweep when it is enabled
     *
     * @return void
     * @throws LocalizedException When the media folders cannot be listed
     */
    public function execute(): void
    {
        if (!$this->mediaConfig->isOrphanSweepEnabled()) {
            return;
        }
        $this->sweeper->sweep(false);
    }
}
