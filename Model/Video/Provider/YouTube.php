<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Video\Provider;

use Hryvinskyi\BannerSliderApi\Api\Value\EmbedKind;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedOptions;
use Hryvinskyi\BannerSliderApi\Api\Value\VideoData;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderInterface;

/**
 * YouTube videos, embedded in an iframe.
 *
 * Understood sources (scheme optional, `www.` or `m.` host prefix allowed):
 * - `youtube.com/watch?…v=ID…`, with `v` anywhere in the query;
 * - `youtu.be/ID`;
 * - `youtube.com/embed/ID`, `youtube-nocookie.com/embed/ID` and `youtube.com/shorts/ID`.
 *
 * A video id is exactly 11 characters of letters, digits, `_` and `-`. The embed uses `youtube-nocookie.com` when
 * the options ask for privacy, and its parameters follow the options: autoplay, mute, loop (with the one-item
 * playlist YouTube needs to loop), hidden controls, always inline playback and related videos from the same channel.
 * The JavaScript API is always on, so the page can pause the player through `postMessage` when its slide leaves view.
 */
class YouTube implements ProviderInterface
{
    public const CODE = 'youtube';

    private const ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';
    private const PATH_PATTERNS = [
        '~^(?:https?://)?youtu\.be/([A-Za-z0-9_-]{11})(?:[/?#]|$)~i',
        '~^(?:https?://)?(?:(?:www|m)\.)?youtube(?:-nocookie)?\.com/(?:embed|shorts)/([A-Za-z0-9_-]{11})(?:[/?#]|$)~i',
    ];
    private const WATCH_PATTERN = '~^(?:https?://)?(?:(?:www|m)\.)?youtube\.com/watch/?\?([^#]*)~i';
    private const HOST = 'www.youtube.com';
    private const PRIVACY_HOST = 'www.youtube-nocookie.com';
    private const ALLOW = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture;'
        . ' web-share';
    private const REFERRER_POLICY = 'strict-origin-when-cross-origin';

    /**
     * @param int $priority Priority among providers that support the same source
     */
    public function __construct(
        private readonly int $priority = 100
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
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @inheritDoc
     */
    public function supports(string $source): bool
    {
        return $this->findVideoId(trim($source)) !== null;
    }

    /**
     * @inheritDoc
     */
    public function parse(string $source): VideoData
    {
        $source = trim($source);
        $videoId = $this->findVideoId($source);
        if ($videoId === null) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a YouTube video URL.', $source));
        }

        return new VideoData(self::CODE, $videoId, $source);
    }

    /**
     * @inheritDoc
     */
    public function getEmbedKind(): EmbedKind
    {
        return EmbedKind::IFRAME;
    }

    /**
     * @inheritDoc
     */
    public function getEmbedUrl(VideoData $data, EmbedOptions $options): string
    {
        $videoId = $this->ownVideoId($data);
        $parameters = [];
        if ($options->isAutoplay()) {
            $parameters['autoplay'] = 1;
        }
        if ($options->isMuted()) {
            $parameters['mute'] = 1;
        }
        if ($options->isLoop()) {
            $parameters['loop'] = 1;
            $parameters['playlist'] = $videoId;
        }
        if (!$options->hasControls()) {
            $parameters['controls'] = 0;
        }
        $parameters['playsinline'] = 1;
        $parameters['rel'] = 0;
        $parameters['enablejsapi'] = 1;

        return sprintf(
            'https://%s/embed/%s?%s',
            $options->isPrivacyEnhanced() ? self::PRIVACY_HOST : self::HOST,
            $videoId,
            http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)
        );
    }

    /**
     * @inheritDoc
     */
    public function getEmbedAttributes(VideoData $data, EmbedOptions $options): array
    {
        $this->ownVideoId($data);

        return [
            'allow' => self::ALLOW,
            'allowfullscreen' => !$options->isBackground(),
            'referrerpolicy' => self::REFERRER_POLICY,
        ];
    }

    /**
     * The video id in a source, or null when the source is not a YouTube video URL
     *
     * @param string $source
     * @return string|null
     */
    private function findVideoId(string $source): ?string
    {
        foreach (self::PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $source, $matches) === 1) {
                return $matches[1];
            }
        }
        if (preg_match(self::WATCH_PATTERN, $source, $matches) !== 1) {
            return null;
        }
        foreach (explode('&', $matches[1]) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if ($name === 'v' && preg_match(self::ID_PATTERN, $value) === 1) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The video id of data this provider parsed
     *
     * @param VideoData $data
     * @return string
     * @throws \InvalidArgumentException When the data belongs to another provider or holds no valid id
     */
    private function ownVideoId(VideoData $data): string
    {
        if ($data->getProviderCode() !== self::CODE || preg_match(self::ID_PATTERN, $data->getVideoId()) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('The video data of provider "%s" is not a YouTube video.', $data->getProviderCode())
            );
        }

        return $data->getVideoId();
    }
}
