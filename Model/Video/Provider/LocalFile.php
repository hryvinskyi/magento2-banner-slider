<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Video\Provider;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedKind;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedOptions;
use Hryvinskyi\BannerSliderApi\Api\Value\VideoData;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderInterface;

/**
 * Uploaded video files of one type, played by a native `<video>` element.
 *
 * One class serves every file type; `di.xml` declares one virtual type per type with its code and extension. A
 * supported source is a safe media-relative path inside the package's `video` root that ends in the extension
 * (case-insensitive). The video id is that path, and the embed URL is its media URL.
 */
class LocalFile implements ProviderInterface
{
    private const VIDEO_ROOT_PURPOSE = 'video';
    private const EXTENSION_PATTERN = '/^[a-z0-9]{1,16}$/';

    /**
     * @param MediaUrlResolverInterface $mediaUrlResolver
     * @param MediaPaths $mediaPaths
     * @param string $code Provider code, such as `local_mp4`
     * @param string $extension File extension without the dot, lowercase
     * @param int $priority Priority among providers that support the same source
     * @throws \InvalidArgumentException When the code is empty or the extension is not lowercase letters or digits
     */
    public function __construct(
        private readonly MediaUrlResolverInterface $mediaUrlResolver,
        private readonly MediaPaths $mediaPaths,
        private readonly string $code,
        private readonly string $extension,
        private readonly int $priority = 10
    ) {
        if (trim($code) === '') {
            throw new \InvalidArgumentException('A local video provider needs a code.');
        }
        if (preg_match(self::EXTENSION_PATTERN, $extension) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'A local video provider needs a lowercase extension without the dot, got "%s".',
                $extension
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
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
        return $this->videoPath(trim($source)) !== null;
    }

    /**
     * @inheritDoc
     */
    public function parse(string $source): VideoData
    {
        $source = trim($source);
        $path = $this->videoPath($source);
        if ($path === null) {
            throw new \InvalidArgumentException(sprintf(
                '"%s" is not a .%s video in the banner slider video folder.',
                $source,
                $this->extension
            ));
        }

        return new VideoData($this->code, $path, $source);
    }

    /**
     * @inheritDoc
     */
    public function getEmbedKind(): EmbedKind
    {
        return EmbedKind::VIDEO;
    }

    /**
     * @inheritDoc
     */
    public function getEmbedUrl(VideoData $data, EmbedOptions $options): string
    {
        return $this->mediaUrlResolver->getUrl($this->ownPath($data));
    }

    /**
     * @inheritDoc
     */
    public function getEmbedAttributes(VideoData $data, EmbedOptions $options): array
    {
        $this->ownPath($data);

        return [
            'autoplay' => $options->isAutoplay(),
            'muted' => $options->isMuted(),
            'loop' => $options->isLoop(),
            'playsinline' => true,
            'controls' => $options->hasControls(),
            'preload' => 'metadata',
        ];
    }

    /**
     * The media path of a source this provider plays, or null when it plays none
     *
     * @param string $source
     * @return string|null
     */
    private function videoPath(string $source): ?string
    {
        try {
            $path = $this->mediaPaths->assertSafe($source);
        } catch (\InvalidArgumentException) {
            return null;
        }
        $insideRoot = str_starts_with($path, $this->mediaPaths->getRoot(self::VIDEO_ROOT_PURPOSE) . '/');
        $suffix = '.' . $this->extension;
        $hasExtension = strlen($path) > strlen($suffix) && str_ends_with(strtolower($path), $suffix);

        return $insideRoot && $hasExtension && !str_ends_with($path, '/' . $suffix) ? $path : null;
    }

    /**
     * The media path of data this provider parsed
     *
     * @param VideoData $data
     * @return string
     * @throws \InvalidArgumentException When the data belongs to another provider or holds no playable path
     */
    private function ownPath(VideoData $data): string
    {
        $path = $data->getProviderCode() === $this->code ? $this->videoPath($data->getVideoId()) : null;
        if ($path === null) {
            throw new \InvalidArgumentException(sprintf(
                'The video data of provider "%s" is not a .%s video of provider "%s".',
                $data->getProviderCode(),
                $this->extension,
                $this->code
            ));
        }

        return $path;
    }
}
