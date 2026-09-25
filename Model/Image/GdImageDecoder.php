<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

/**
 * Decodes image bytes into a GD image, the one place core turns bytes into pixels through GD.
 *
 * Decoding reads every pixel, so a truncated or corrupt body fails here even when its header reads. The decoded image
 * is true colour with its alpha channel kept, ready for any GD writer. Which formats this GD build reads is asked of
 * the runtime per format (`di.xml` maps a format code to the `gd_info()` feature that reports it).
 */
class GdImageDecoder
{
    private const DECODE_FUNCTION = 'imagecreatefromstring';

    /**
     * @param RuntimeCapabilities $runtime
     * @param array<string,string> $readableFormats Format code => `gd_info()` feature telling GD reads the format
     */
    public function __construct(
        private readonly RuntimeCapabilities $runtime,
        private readonly array $readableFormats = []
    ) {
    }

    /**
     * Whether this runtime's GD can decode images of the format
     *
     * @param string $formatCode
     * @return bool
     */
    public function canDecode(string $formatCode): bool
    {
        $feature = $this->readableFormats[$formatCode] ?? null;

        return $feature !== null
            && $this->runtime->hasFunction(self::DECODE_FUNCTION)
            && $this->runtime->gdSupports($feature);
    }

    /**
     * Decode the bytes into a true-colour GD image that keeps its alpha channel
     *
     * @param string $bytes
     * @return \GdImage
     * @throws EncodingException When GD cannot decode the bytes
     */
    public function decode(string $bytes): \GdImage
    {
        try {
            $image = $bytes === '' ? false : imagecreatefromstring($bytes);
            if ($image === false) {
                throw new EncodingException(__('GD could not decode the source image.'));
            }
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
        } catch (EncodingException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new EncodingException(
                __('GD could not decode the source image.'),
                $exception instanceof \Exception ? $exception : null
            );
        }

        return $image;
    }
}
