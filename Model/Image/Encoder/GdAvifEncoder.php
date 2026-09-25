<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Encoder;

/**
 * Writes AVIF at the given quality through GD.
 */
class GdAvifEncoder extends AbstractGdEncoder
{
    private const FORMAT_CODE = 'avif';

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
        return 'imageavif';
    }

    /**
     * @inheritDoc
     */
    protected function getGdFeature(): string
    {
        return 'AVIF Support';
    }

    /**
     * @inheritDoc
     */
    protected function write(\GdImage $image, string $destinationLocalPath, int $quality): bool
    {
        return imageavif($image, $destinationLocalPath, $quality);
    }
}
