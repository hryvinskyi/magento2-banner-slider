<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Encoder;

use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\ImagickCreator;
use Hryvinskyi\BannerSlider\Model\Image\RuntimeCapabilities;

/**
 * Writes AVIF at the given quality through Imagick, when its ImageMagick build has an AVIF coder.
 *
 * The Imagick object is released whether the encode succeeds or fails.
 */
class ImagickAvifEncoder implements ImageEncoderInterface
{
    private const FORMAT_CODE = 'avif';
    private const IMAGICK_FORMAT = 'AVIF';
    private const MIN_QUALITY = 1;
    private const MAX_QUALITY = 100;

    /**
     * @param RuntimeCapabilities $runtime
     * @param ImagickCreator $imagickCreator
     */
    public function __construct(
        private readonly RuntimeCapabilities $runtime,
        private readonly ImagickCreator $imagickCreator
    ) {
    }

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
    public function isAvailable(): bool
    {
        return $this->runtime->imagickSupports(self::IMAGICK_FORMAT);
    }

    /**
     * @inheritDoc
     */
    public function encode(string $sourceLocalPath, string $destinationLocalPath, int $quality): void
    {
        $imagick = $this->imagickCreator->create();
        try {
            $imagick->readImage($sourceLocalPath);
            $imagick->setImageFormat(self::IMAGICK_FORMAT);
            $imagick->setImageCompressionQuality(max(self::MIN_QUALITY, min(self::MAX_QUALITY, $quality)));
            if (!$imagick->writeImage($destinationLocalPath)) {
                throw new EncodingException(__('Imagick could not encode the image as %1.', self::FORMAT_CODE));
            }
        } catch (\ImagickException $e) {
            throw new EncodingException(__('Imagick could not encode the image as %1.', self::FORMAT_CODE), $e);
        } finally {
            $imagick->clear();
        }
    }
}
