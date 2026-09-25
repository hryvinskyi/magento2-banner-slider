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
 * Vimeo videos, embedded in an iframe.
 *
 * Understood sources (scheme optional, `www.` allowed): `vimeo.com/ID`, `vimeo.com/ID/HASH` (an unlisted video) and
 * `player.vimeo.com/video/ID`, optionally with `?h=HASH`. The video id is the numeric id; the unlisted hash, when
 * the source has one, is read back from the source URL and passed as `h`. The embed parameters follow the options:
 * `background` (Vimeo's own chromeless looping mode), autoplay, muted, loop, and `dnt` for privacy.
 */
class Vimeo implements ProviderInterface
{
    public const CODE = 'vimeo';

    private const ID_PATTERN = '/^\d{1,20}$/';
    private const SOURCE_PATTERNS = [
        '~^(?:https?://)?(?:www\.)?vimeo\.com/(\d{1,20})(?:/([0-9a-f]{6,40}))?/?(?:[?#]|$)~i',
        '~^(?:https?://)?player\.vimeo\.com/video/(\d{1,20})/?(?:\?(?:[^#]*&)?h=([0-9a-f]{6,40})(?:[&#]|$)|[?#]|$)~i',
    ];
    private const ALLOW = 'autoplay; fullscreen; picture-in-picture';
    private const REFERRER_POLICY = 'strict-origin-when-cross-origin';

    /**
     * @param int $priority Priority among providers that support the same source
     */
    public function __construct(
        private readonly int $priority = 90
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
        return $this->match(trim($source)) !== null;
    }

    /**
     * @inheritDoc
     */
    public function parse(string $source): VideoData
    {
        $source = trim($source);
        $match = $this->match($source);
        if ($match === null) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a Vimeo video URL.', $source));
        }

        return new VideoData(self::CODE, $match['id'], $source);
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
        $hash = $this->match($data->getSourceUrl())['hash'] ?? null;
        if ($hash !== null) {
            $parameters['h'] = $hash;
        }
        if ($options->isBackground()) {
            $parameters['background'] = 1;
        }
        if ($options->isAutoplay()) {
            $parameters['autoplay'] = 1;
        }
        if ($options->isMuted()) {
            $parameters['muted'] = 1;
        }
        if ($options->isLoop()) {
            $parameters['loop'] = 1;
        }
        if ($options->isPrivacyEnhanced()) {
            $parameters['dnt'] = 1;
        }
        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return 'https://player.vimeo.com/video/' . $videoId . ($query === '' ? '' : '?' . $query);
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
     * The video id and unlisted hash in a source, or null when the source is not a Vimeo video URL
     *
     * @param string $source
     * @return array{id: string, hash: string|null}|null
     */
    private function match(string $source): ?array
    {
        foreach (self::SOURCE_PATTERNS as $pattern) {
            if (preg_match($pattern, $source, $matches) === 1) {
                $hash = $matches[2] ?? '';

                return ['id' => $matches[1], 'hash' => $hash === '' ? null : strtolower($hash)];
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
                sprintf('The video data of provider "%s" is not a Vimeo video.', $data->getProviderCode())
            );
        }

        return $data->getVideoId();
    }
}
