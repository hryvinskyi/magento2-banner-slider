<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Encoder;

use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\GdImageDecoder;
use Hryvinskyi\BannerSlider\Model\Image\RuntimeCapabilities;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;

/**
 * Encodes through the GD extension: decodes any image GD reads, keeps alpha, and writes one format.
 *
 * GD recognises the source format from its bytes, so every source type is decoded the same way (GdImageDecoder). A
 * palette image is turned into true colour first, which the WebP and AVIF writers require.
 */
abstract class AbstractGdEncoder implements ImageEncoderInterface
{
    private const MIN_QUALITY = 1;
    private const MAX_QUALITY = 100;

    /**
     * @param RuntimeCapabilities $runtime
     * @param LocalFileDriver $localDriver
     * @param GdImageDecoder $decoder
     */
    public function __construct(
        private readonly RuntimeCapabilities $runtime,
        private readonly LocalFileDriver $localDriver,
        private readonly GdImageDecoder $decoder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->runtime->hasFunction('imagecreatefromstring')
            && $this->runtime->hasFunction($this->getWriteFunction())
            && $this->runtime->gdSupports($this->getGdFeature());
    }

    /**
     * @inheritDoc
     */
    public function encode(string $sourceLocalPath, string $destinationLocalPath, int $quality): void
    {
        $quality = max(self::MIN_QUALITY, min(self::MAX_QUALITY, $quality));
        try {
            $bytes = $this->localDriver->fileGetContents($sourceLocalPath);
        } catch (FileSystemException $e) {
            throw new EncodingException(__('The source image could not be read for encoding.'), $e);
        }

        $image = $this->decoder->decode($bytes);
        try {
            $written = $this->write($image, $destinationLocalPath, $quality);
        } catch (\Exception $e) {
            throw new EncodingException(__('GD could not encode the image as %1.', $this->getFormatCode()), $e);
        }

        if (!$written) {
            throw new EncodingException(__('GD could not encode the image as %1.', $this->getFormatCode()));
        }
    }

    /**
     * Name of the GD function that writes the format; its absence makes the encoder unavailable
     *
     * @return string
     */
    abstract protected function getWriteFunction(): string;

    /**
     * The `gd_info()` key that reports support for the format
     *
     * @return string
     */
    abstract protected function getGdFeature(): string;

    /**
     * Write the decoded image to the destination
     *
     * @param \GdImage $image
     * @param string $destinationLocalPath
     * @param int $quality 1..100
     * @return bool Whether GD reported success
     */
    abstract protected function write(\GdImage $image, string $destinationLocalPath, int $quality): bool;
}
