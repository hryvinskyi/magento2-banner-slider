<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Config;

use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Image settings from `hryvinskyi_banner_slider/image/*`, read in the default scope.
 *
 * Every getter survives a broken stored value:
 * - default formats: only registered variant formats are kept, in the registry's preference order; an empty
 *   selection means "no extra formats", a missing one falls back to WebP;
 * - quality: `<format>_quality`, clamped to 1..100; a value that is not a number gives the format's fallback
 *   quality. Only formats with a fallback quality (configured in `di.xml`) have a quality setting at all;
 * - upload cap: `max_upload_size_mb`, 10 MB when the value is not a positive number.
 */
class ImageConfig implements ImageConfigInterface
{
    private const XML_PATH_DEFAULT_FORMATS = 'hryvinskyi_banner_slider/image/default_formats';
    private const XML_PATH_QUALITY = 'hryvinskyi_banner_slider/image/%s_quality';
    private const XML_PATH_MAX_UPLOAD_SIZE = 'hryvinskyi_banner_slider/image/max_upload_size_mb';
    private const FALLBACK_DEFAULT_FORMATS = ['webp'];
    private const FALLBACK_MAX_UPLOAD_MB = 10.0;
    private const MIN_QUALITY = 1;
    private const MAX_QUALITY = 100;

    /**
     * @var array<string,int>
     */
    private readonly array $fallbackQualities;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param UploadLimitReader $uploadLimitReader
     * @param array<string,int|string> $fallbackQualities Quality per format code when the stored one is not a number
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly UploadLimitReader $uploadLimitReader,
        array $fallbackQualities = []
    ) {
        $qualities = [];
        foreach ($fallbackQualities as $code => $quality) {
            $qualities[$code] = $this->clamp((int)$quality);
        }
        $this->fallbackQualities = $qualities;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultVariantFormats(): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_DEFAULT_FORMATS);
        $requested = is_string($value) ? explode(',', $value) : self::FALLBACK_DEFAULT_FORMATS;
        $requested = array_map(static fn (string $code): string => strtolower(trim($code)), $requested);

        $codes = [];
        foreach ($this->formatRegistry->getVariantFormats() as $format) {
            if (in_array($format->getCode(), $requested, true)) {
                $codes[] = $format->getCode();
            }
        }

        return $codes;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultQuality(string $formatCode): int
    {
        if (!isset($this->fallbackQualities[$formatCode])
            || preg_match(ImageFormat::CODE_PATTERN, $formatCode) !== 1
        ) {
            throw new \InvalidArgumentException(
                sprintf('The image format "%s" has no quality setting.', $formatCode)
            );
        }
        $value = $this->scopeConfig->getValue(sprintf(self::XML_PATH_QUALITY, $formatCode));

        return is_numeric($value)
            ? $this->clamp((int)round((float)$value))
            : $this->fallbackQualities[$formatCode];
    }

    /**
     * @inheritDoc
     */
    public function getMaxUploadBytes(): int
    {
        return $this->uploadLimitReader->readBytes(self::XML_PATH_MAX_UPLOAD_SIZE, self::FALLBACK_MAX_UPLOAD_MB);
    }

    /**
     * A quality limited to the range encoders accept
     *
     * @param int $quality
     * @return int
     */
    private function clamp(int $quality): int
    {
        return max(self::MIN_QUALITY, min(self::MAX_QUALITY, $quality));
    }
}
