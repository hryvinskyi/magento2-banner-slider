<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\GdImageDecoder;
use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\EncodedImage;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;

/**
 * Checks an image encoded outside the server (by the admin browser) before its bytes may become a crop file.
 *
 * The bytes are untrusted. In this order they must:
 * - be no larger than the image upload cap;
 * - declare a registered format;
 * - really be that format, sniffed from the bytes;
 * - read as an image of that format, from its header;
 * - have the crop's target size, give or take one pixel for rounding in the browser;
 * - decode completely, pixel by pixel, wherever this server's GD reads the format, so a truncated or corrupt body is
 *   refused although its header reads. A format GD cannot read here is checked by its header only.
 */
class EncodedImageValidator
{
    private const SIZE_TOLERANCE = 1;

    /**
     * @param ImageConfigInterface $imageConfig
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param ImageInspector $imageInspector
     * @param LocalFileWorkspace $workspace
     * @param LocalFileDriver $localDriver
     * @param GdImageDecoder $decoder
     */
    public function __construct(
        private readonly ImageConfigInterface $imageConfig,
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly ImageInspector $imageInspector,
        private readonly LocalFileWorkspace $workspace,
        private readonly LocalFileDriver $localDriver,
        private readonly GdImageDecoder $decoder
    ) {
    }

    /**
     * The format of an encoded image that passed every check
     *
     * @param EncodedImage $image
     * @param BreakpointInterface $breakpoint The breakpoint the image is a crop for, named in the messages
     * @param Dimensions $target The size the crop is rendered at
     * @return ImageFormat
     * @throws LocalizedException Naming the breakpoint and the reason when a check fails
     */
    public function validate(EncodedImage $image, BreakpointInterface $breakpoint, Dimensions $target): ImageFormat
    {
        $identifier = $breakpoint->getIdentifier();
        $code = $image->getFormatCode();
        $bytes = $image->getBytes();
        $maxBytes = $this->imageConfig->getMaxUploadBytes();
        if (strlen($bytes) > $maxBytes) {
            throw new LocalizedException(__(
                'The %1 image supplied for breakpoint "%2" is larger than %3 bytes.',
                $code,
                $identifier,
                $maxBytes
            ));
        }
        if (!$this->formatRegistry->has($code)) {
            throw new LocalizedException(__(
                'The image supplied for breakpoint "%1" declares the unknown format "%2".',
                $identifier,
                $code
            ));
        }
        $format = $this->formatRegistry->get($code);
        $sniffed = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!is_string($sniffed) || $this->formatRegistry->getByMimeType($sniffed)?->getCode() !== $code) {
            throw new LocalizedException(__(
                'The image supplied for breakpoint "%1" as %2 is really of type "%3".',
                $identifier,
                $code,
                is_string($sniffed) ? $sniffed : 'unknown'
            ));
        }

        $size = $this->decodedSize($bytes, $format);
        if ($size === null) {
            throw new LocalizedException(__(
                'The %1 image supplied for breakpoint "%2" cannot be decoded.',
                $code,
                $identifier
            ));
        }
        if (abs($size->getWidth() - $target->getWidth()) > self::SIZE_TOLERANCE
            || abs($size->getHeight() - $target->getHeight()) > self::SIZE_TOLERANCE
        ) {
            throw new LocalizedException(__(
                'The %1 image supplied for breakpoint "%2" is %3x%4 pixels; the breakpoint needs %5x%6.',
                $code,
                $identifier,
                $size->getWidth(),
                $size->getHeight(),
                $target->getWidth(),
                $target->getHeight()
            ));
        }
        $this->assertDecodes($bytes, $code, $identifier);

        return $format;
    }

    /**
     * Decode the bytes completely when GD reads their format on this server
     *
     * @param string $bytes
     * @param string $code
     * @param string $identifier
     * @return void
     * @throws LocalizedException When the bytes do not decode
     */
    private function assertDecodes(string $bytes, string $code, string $identifier): void
    {
        if (!$this->decoder->canDecode($code)) {
            return;
        }
        try {
            $this->decoder->decode($bytes);
        } catch (EncodingException $exception) {
            throw new LocalizedException(
                __('The %1 image supplied for breakpoint "%2" cannot be decoded.', $code, $identifier),
                $exception
            );
        }
    }

    /**
     * The pixel size of the bytes decoded from a local temp copy, or null when they do not decode as the format
     *
     * @param string $bytes
     * @param ImageFormat $format
     * @return Dimensions|null
     * @throws LocalizedException When the temp copy cannot be written or removed
     */
    private function decodedSize(string $bytes, ImageFormat $format): ?Dimensions
    {
        $localPath = $this->workspace->newTempPath($format->getExtension());
        try {
            $this->localDriver->filePutContents($localPath, $bytes);
            $info = $this->imageInspector->inspect($localPath);
        } finally {
            $this->workspace->discard($localPath);
        }
        if ($info === null || $this->formatRegistry->getByMimeType($info['mime'])?->getCode() !== $format->getCode()) {
            return null;
        }

        return new Dimensions($info['width'], $info['height']);
    }
}
