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

/**
 * YouTube video provider
 */
class YouTube extends AbstractProvider
{
    private const CODE = 'youtube';
    private const EMBED_URL_TEMPLATE = 'https://www.youtube.com/embed/%s';
    private const THUMBNAIL_URL_TEMPLATE = 'https://img.youtube.com/vi/%s/maxresdefault.jpg';

    /**
     * YouTube URL patterns
     */
    private const URL_PATTERNS = [
        '/^(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        '/^(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/^(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]+)/',
        '/^(?:https?:\/\/)?(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/',
    ];

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
        foreach (self::URL_PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function parse(string $url): VideoDataInterface
    {
        $videoId = null;

        foreach (self::URL_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $videoId = $matches[1];
                break;
            }
        }

        if ($videoId === null) {
            throw new \InvalidArgumentException(
                sprintf('Could not parse YouTube video ID from URL: %s', $url)
            );
        }

        return new VideoData(
            provider: self::CODE,
            videoId: $videoId,
            originalUrl: $url,
            aspectRatio: '16:9',
            thumbnailUrl: sprintf(self::THUMBNAIL_URL_TEMPLATE, $videoId)
        );
    }

    /**
     * @inheritDoc
     */
    public function getEmbedUrl(VideoDataInterface $videoData): string
    {
        return sprintf(self::EMBED_URL_TEMPLATE, $videoData->getVideoId());
    }

    /**
     * @inheritDoc
     */
    public function getEmbedAttributes(): array
    {
        return array_merge(parent::getEmbedAttributes(), [
            'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
        ]);
    }
}
