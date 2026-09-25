<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Encoder;

/**
 * Writes AVIF through the `cavif` command-line encoder, which reads JPEG and PNG sources.
 */
class CavifEncoder extends AbstractBinaryEncoder
{
    private const FORMAT_CODE = 'avif';
    private const BINARY = 'cavif';

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
            '--quiet',
            '--overwrite',
            '--quality', (string)$quality,
            '--output', $destinationLocalPath,
        ];
    }
}
