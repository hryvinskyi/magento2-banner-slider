<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Encoder;

use Hryvinskyi\BannerSlider\Model\Image\EncodingException;

/**
 * Writes an image file in one format, using one library or tool.
 *
 * Encoders are registered per format in the image converter's `di.xml` pool, in the order they are tried. Adding a
 * way to produce a format means adding an encoder there; no caller changes.
 */
interface ImageEncoderInterface
{
    /**
     * Code of the format this encoder writes, as the format registry names it (such as `webp`)
     *
     * @return string
     */
    public function getFormatCode(): string;

    /**
     * Whether this server has what the encoder needs (an extension, a function, a binary)
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Encode a local image file into the destination path
     *
     * @param string $sourceLocalPath Absolute path of a readable image
     * @param string $destinationLocalPath Absolute path to write; its folder exists
     * @param int $quality 1..100; values outside are clamped; lossless formats ignore it
     * @return void
     * @throws EncodingException When the source cannot be decoded or the output cannot be written
     */
    public function encode(string $sourceLocalPath, string $destinationLocalPath, int $quality): void;
}
