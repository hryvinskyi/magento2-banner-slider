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
 * Reads an upload size limit the admin enters in megabytes and returns it in bytes.
 *
 * A stored value that is not a positive number (empty, text, zero, negative) gives the caller's fallback instead.
 */
class UploadLimitReader
{
    private const BYTES_PER_MB = 1048576;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * The limit in bytes stored at a configuration path, in the default scope
     *
     * @param string $path Configuration path of a value in megabytes
     * @param float $fallbackMegabytes Used when the stored value is not a positive number
     * @return int
     */
    public function readBytes(string $path, float $fallbackMegabytes): int
    {
        $value = $this->scopeConfig->getValue($path);
        $megabytes = is_numeric($value) && (float)$value > 0 ? (float)$value : $fallbackMegabytes;

        return max(1, (int)round($megabytes * self::BYTES_PER_MB));
    }
}
