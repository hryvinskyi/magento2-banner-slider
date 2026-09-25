<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Config;

use Hryvinskyi\BannerSliderApi\Api\Config\VideoConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Video settings from `hryvinskyi_banner_slider/video/*`.
 *
 * - `privacy_enhanced` is read per store view; anything but an explicit off (`0`) keeps the privacy-preserving
 *   embeds on, so a broken value never starts sending visitor data to a video platform.
 * - `max_upload_size_mb` is read in the default scope; 100 MB when the value is not a positive number.
 */
class VideoConfig implements VideoConfigInterface
{
    private const XML_PATH_PRIVACY_ENHANCED = 'hryvinskyi_banner_slider/video/privacy_enhanced';
    private const XML_PATH_MAX_UPLOAD_SIZE = 'hryvinskyi_banner_slider/video/max_upload_size_mb';
    private const FALLBACK_MAX_UPLOAD_MB = 100.0;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param UploadLimitReader $uploadLimitReader
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UploadLimitReader $uploadLimitReader
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isPrivacyEnhanced(?int $storeId = null): bool
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_PRIVACY_ENHANCED, ScopeInterface::SCOPE_STORE, $storeId);

        return !in_array($value, ['0', 0, false], true);
    }

    /**
     * @inheritDoc
     */
    public function getMaxUploadBytes(): int
    {
        return $this->uploadLimitReader->readBytes(self::XML_PATH_MAX_UPLOAD_SIZE, self::FALLBACK_MAX_UPLOAD_MB);
    }
}
