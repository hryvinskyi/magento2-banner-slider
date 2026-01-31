<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Video;

use Hryvinskyi\BannerSliderApi\Api\Video\VideoDataInterface;

/**
 * Video data transfer object
 */
class VideoData implements VideoDataInterface
{
    /**
     * @param string $provider
     * @param string $videoId
     * @param string $originalUrl
     * @param string $aspectRatio
     * @param string|null $thumbnailUrl
     */
    public function __construct(
        private readonly string $provider,
        private readonly string $videoId,
        private readonly string $originalUrl,
        private readonly string $aspectRatio = '16:9',
        private readonly ?string $thumbnailUrl = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * @inheritDoc
     */
    public function getVideoId(): string
    {
        return $this->videoId;
    }

    /**
     * @inheritDoc
     */
    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    /**
     * @inheritDoc
     */
    public function getAspectRatio(): string
    {
        return $this->aspectRatio;
    }

    /**
     * @inheritDoc
     */
    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }
}
