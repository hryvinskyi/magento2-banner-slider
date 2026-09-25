<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Encoder;

/**
 * Writes lossy WebP through the `cwebp` command-line encoder, tuned for photos with full-quality alpha.
 */
class CwebpEncoder extends AbstractBinaryEncoder
{
    private const FORMAT_CODE = 'webp';
    private const BINARY = 'cwebp';

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
    protected function getBinaryName(): string
    {
        return self::BINARY;
    }

    /**
     * @inheritDoc
     */
    protected function getOptions(int $quality, string $destinationLocalPath): array
    {
        return [
            '-quiet',
            '-q', (string)$quality,
            '-alpha_q', '100',
            '-m', '6',
            '-segments', '4',
            '-sns', '80',
            '-f', '25',
            '-sharpness', '0',
            '-strong',
            '-pass', '10',
            '-mt',
            '-alpha_method', '1',
            '-alpha_filter', 'fast',
            '-o', $destinationLocalPath,
        ];
    }
}
