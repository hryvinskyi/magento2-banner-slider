<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Encoder;

/**
 * Writes lossy WebP at the given quality through GD.
 */
class GdWebpEncoder extends AbstractGdEncoder
{
    private const FORMAT_CODE = 'webp';

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
        return 'imagewebp';
    }

    /**
     * @inheritDoc
     */
    protected function getGdFeature(): string
    {
        return 'WebP Support';
    }

    /**
     * @inheritDoc
     */
    protected function write(\GdImage $image, string $destinationLocalPath, int $quality): bool
    {
        return imagewebp($image, $destinationLocalPath, $quality);
    }
}
