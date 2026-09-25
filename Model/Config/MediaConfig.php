<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Media housekeeping settings from `hryvinskyi_banner_slider/media/*`, read in the default scope.
 *
 * Only this package reads them, so they are not part of the published API. The orphan sweep deletes files, so it
 * runs only on an explicit on (`1`); a missing or broken value keeps it off.
 */
class MediaConfig
{
    private const XML_PATH_ORPHAN_SWEEP_ENABLED = 'hryvinskyi_banner_slider/media/orphan_sweep_enabled';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether the scheduled sweep may delete media files no row references
     *
     * @return bool
     */
    public function isOrphanSweepEnabled(): bool
    {
        return in_array($this->scopeConfig->getValue(self::XML_PATH_ORPHAN_SWEEP_ENABLED), ['1', 1, true], true);
    }
}
