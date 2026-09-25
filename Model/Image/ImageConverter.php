<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\Encoder\ImageEncoderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use Psr\Log\LoggerInterface;

/**
 * Encodes a local image into a format through the encoders registered for it in `di.xml`.
 *
 * Encoders are tried in their configured order and the first available one wins. When it fails, or leaves no file
 * behind, the failure is logged and the next available encoder is tried. Only when none succeeds does the conversion
 * fail. Every encoder must write the format it is registered under; a mismatch fails loudly when the converter is
 * built.
 */
class ImageConverter
{
    /**
     * @param LoggerInterface $logger
     * @param LocalFileDriver $localDriver
     * @param array<string,array<string,ImageEncoderInterface>> $encoders Encoders by format code, in trial order
     * @throws \InvalidArgumentException When an encoder is registered under a format it does not write
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly LocalFileDriver $localDriver,
        private readonly array $encoders = []
    ) {
        foreach ($encoders as $formatCode => $formatEncoders) {
            foreach ($formatEncoders as $name => $encoder) {
                if ($encoder->getFormatCode() !== $formatCode) {
                    throw new \InvalidArgumentException(sprintf(
                        'The image encoder "%s" writes "%s" but is registered for "%s".',
                        $name,
                        $encoder->getFormatCode(),
                        $formatCode
                    ));
                }
            }
        }
    }

    /**
     * Encode a local image into the format
     *
     * @param string $sourceLocalPath Absolute path of a readable image
     * @param ImageFormat $format
     * @param int $quality 1..100; lossless formats ignore it
     * @param string $destinationLocalPath Absolute path to write; its folder exists
     * @return void
     * @throws EncodingException When no encoder is available or every available one failed
     */
    public function convert(
        string $sourceLocalPath,
        ImageFormat $format,
        int $quality,
        string $destinationLocalPath
    ): void {
        $lastFailure = null;
        foreach ($this->availableEncoders($format) as $name => $encoder) {
            try {
                $encoder->encode($sourceLocalPath, $destinationLocalPath, $quality);
                $this->assertWritten($destinationLocalPath);

                return;
            } catch (EncodingException $e) {
                $lastFailure = $e;
                $this->logger->warning(
                    sprintf('Banner slider: the "%s" encoder failed to write %s.', $name, $format->getCode()),
                    ['exception' => $e]
                );
            }
        }

        throw new EncodingException(__('The image could not be encoded as %1.', $format->getCode()), $lastFailure);
    }

    /**
     * Whether at least one encoder of the format is available on this server
     *
     * @param ImageFormat $format
     * @return bool
     */
    public function isEncodable(ImageFormat $format): bool
    {
        return $this->availableEncoders($format) !== [];
    }

    /**
     * The available encoders of a format, in trial order
     *
     * @param ImageFormat $format
     * @return array<string,ImageEncoderInterface>
     */
    private function availableEncoders(ImageFormat $format): array
    {
        return array_filter(
            $this->encoders[$format->getCode()] ?? [],
            static fn (ImageEncoderInterface $encoder): bool => $encoder->isAvailable()
        );
    }

    /**
     * Confirm the encoder left a non-empty file at the destination
     *
     * @param string $destinationLocalPath
     * @return void
     * @throws EncodingException When there is no file or it is empty
     */
    private function assertWritten(string $destinationLocalPath): void
    {
        try {
            $size = $this->localDriver->stat($destinationLocalPath)['size'] ?? 0;
        } catch (FileSystemException $e) {
            throw new EncodingException(__('The encoder reported success but wrote no file.'), $e);
        }
        if (!is_int($size) || $size < 1) {
            throw new EncodingException(__('The encoder reported success but wrote an empty file.'));
        }
    }
}
