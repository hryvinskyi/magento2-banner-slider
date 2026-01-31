<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Video\Provider;

use Hryvinskyi\BannerSlider\Model\Video\VideoData;
use Hryvinskyi\BannerSliderApi\Api\Video\VideoDataInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\VideoPathConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Local WebM video provider
 */
class LocalWebm extends AbstractProvider
{
    private const CODE = 'local_webm';

    /**
     * @param StoreManagerInterface $storeManager
     * @param VideoPathConfigInterface $videoPathConfig
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly VideoPathConfigInterface $videoPathConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return self::CODE;
    }

    /**
     * @inheritDoc
     */
    public function supports(string $url): bool
    {
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        return $extension === 'webm';
    }

    /**
     * @inheritDoc
     */
    public function parse(string $url): VideoDataInterface
    {
        return new VideoData(
            provider: self::CODE,
            videoId: $url,
            originalUrl: $url,
            aspectRatio: '16:9',
            thumbnailUrl: null
        );
    }

    /**
     * @inheritDoc
     */
    public function getEmbedUrl(VideoDataInterface $videoData): string
    {
        $videoPath = $videoData->getVideoId();

        if (str_starts_with($videoPath, 'http://') || str_starts_with($videoPath, 'https://')) {
            return $videoPath;
        }

        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        return $mediaUrl . $this->videoPathConfig->getBasePath() . '/' . ltrim($videoPath, '/');
    }

    /**
     * @inheritDoc
     */
    public function getEmbedAttributes(): array
    {
        return [
            'controls' => 'controls',
            'playsinline' => 'playsinline',
        ];
    }

    /**
     * @inheritDoc
     */
    public function isLocal(): bool
    {
        return true;
    }
}
