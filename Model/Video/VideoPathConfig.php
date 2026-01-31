<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Video;

use Hryvinskyi\BannerSliderApi\Api\Video\VideoPathConfigInterface;

/**
 * Video path configuration
 */
class VideoPathConfig implements VideoPathConfigInterface
{
    private const BASE_PATH = 'banner_slider/video';
    private const TMP_PATH = 'banner_slider/tmp/video';
    private const MAX_FILE_SIZE = 104857600; // 100 MB

    private const ALLOWED_MIME_TYPES = [
        'video/mp4',
        'video/webm',
        'video/ogg',
    ];

    private const ALLOWED_EXTENSIONS = [
        'mp4',
        'webm',
        'ogv',
    ];

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getBasePath(): string
    {
        return self::BASE_PATH;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getTmpPath(): string
    {
        return self::TMP_PATH;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getAllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }
}
