<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointInput;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;

/**
 * Names crop output files: `<crop output folder>/<banner id>/<breakpoint identifier>_<hash>.<extension>`.
 *
 * - The hash is the SHA-256 of the encoded bytes, cut to twelve characters, so any change of source, rectangle,
 *   target size, format or quality gives a new name and a cached copy of the old file is never served for the new one.
 * - The extension is the format's canonical one from the registry.
 * - Nothing in the name comes from request data unchecked: the identifier is checked against the breakpoint
 *   identifier rule again here, so a stored value that slipped past it can never reach a file path.
 */
class CropFileNamer
{
    private const OUTPUT_ROOT = 'responsive';
    private const HASH_LENGTH = 12;
    private const HASH_PATTERN = '/^[a-f0-9]{12,}$/';

    /**
     * @param MediaPaths $mediaPaths
     */
    public function __construct(
        private readonly MediaPaths $mediaPaths
    ) {
    }

    /**
     * The content hash of encoded image bytes
     *
     * @param string $bytes
     * @return string Lowercase hexadecimal SHA-256
     */
    public function contentHash(string $bytes): string
    {
        return hash('sha256', $bytes);
    }

    /**
     * The media-relative path of a crop output file
     *
     * @param int $bannerId Greater than 0
     * @param string $breakpointIdentifier Must match the breakpoint identifier rule
     * @param string $contentHash Lowercase hexadecimal hash of the file's bytes, at least twelve characters
     * @param ImageFormat $format
     * @return string
     * @throws \InvalidArgumentException When the banner id, identifier or hash breaks its rule
     */
    public function name(int $bannerId, string $breakpointIdentifier, string $contentHash, ImageFormat $format): string
    {
        if ($bannerId < 1) {
            throw new \InvalidArgumentException(
                sprintf('A crop file needs a banner id greater than 0, got %d.', $bannerId)
            );
        }
        if (preg_match(BreakpointInput::IDENTIFIER_PATTERN, $breakpointIdentifier) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('The breakpoint identifier "%s" cannot be used in a crop file name.', $breakpointIdentifier)
            );
        }
        if (preg_match(self::HASH_PATTERN, $contentHash) !== 1) {
            throw new \InvalidArgumentException('A crop file name needs a lowercase hexadecimal content hash.');
        }

        return sprintf(
            '%s/%d/%s_%s.%s',
            $this->mediaPaths->getRoot(self::OUTPUT_ROOT),
            $bannerId,
            $breakpointIdentifier,
            substr($contentHash, 0, self::HASH_LENGTH),
            $format->getExtension()
        );
    }
}
