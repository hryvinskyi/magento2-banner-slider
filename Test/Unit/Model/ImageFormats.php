<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model;

use Hryvinskyi\BannerSlider\Model\Image\ImageConverter;
use Hryvinskyi\BannerSlider\Model\Image\ImageFormatRegistry;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use Psr\Log\LoggerInterface;

/**
 * The format registry the module ships (JPEG, PNG, GIF, and WebP and AVIF as variants), with no encoder behind it.
 */
trait ImageFormats
{
    /**
     * A registry with the shipped format definitions
     *
     * @return ImageFormatRegistry
     */
    private function formatRegistry(): ImageFormatRegistry
    {
        return new ImageFormatRegistry(
            new ImageConverter($this->createMock(LoggerInterface::class), new LocalFileDriver()),
            [
                'jpeg' => ['mime' => 'image/jpeg', 'extension' => 'jpg', 'aliases' => ['jpeg', 'jpe']],
                'png' => ['mime' => 'image/png', 'extension' => 'png'],
                'gif' => ['mime' => 'image/gif', 'extension' => 'gif'],
                'webp' => ['mime' => 'image/webp', 'extension' => 'webp', 'variant' => true, 'preference' => 10],
                'avif' => ['mime' => 'image/avif', 'extension' => 'avif', 'variant' => true, 'preference' => 20],
            ]
        );
    }

    /**
     * One shipped format
     *
     * @param string $code
     * @return ImageFormat
     */
    private function format(string $code): ImageFormat
    {
        return $this->formatRegistry()->get($code);
    }
}
