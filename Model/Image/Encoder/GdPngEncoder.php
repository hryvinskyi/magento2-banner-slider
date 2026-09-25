<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Encoder;

/**
 * Writes PNG through GD. PNG is lossless, so the quality is ignored and the highest compression level is used.
 */
class GdPngEncoder extends AbstractGdEncoder
{
    private const FORMAT_CODE = 'png';

    private const COMPRESSION_LEVEL = 9;

    /**
     * @inheritDoc
     */
    public function getFormatCode(): string
    {
        return self::FORMAT_CODE;
    }

    /**
     * @inheritDoc
     */
    protected function getWriteFunction(): string
    {
        return 'imagepng';
    }

    /**
     * @inheritDoc
     */
    protected function getGdFeature(): string
    {
        return 'PNG Support';
    }

    /**
     * @inheritDoc
     */
    protected function write(\GdImage $image, string $destinationLocalPath, int $quality): bool
    {
        return imagepng($image, $destinationLocalPath, self::COMPRESSION_LEVEL);
    }
}
