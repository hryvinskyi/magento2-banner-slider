<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\Converter\BinaryAvifConverter;
use Hryvinskyi\BannerSlider\Model\Image\Converter\BinaryWebpConverter;
use Hryvinskyi\BannerSliderApi\Api\Image\FormatConverterInterface;
use Psr\Log\LoggerInterface;

/**
 * Image format converter for WebP and AVIF with binary fallback support
 */
class FormatConverter implements FormatConverterInterface
{
    /**
     * @param LoggerInterface $logger
     * @param BinaryWebpConverter $binaryWebpConverter
     * @param BinaryAvifConverter $binaryAvifConverter
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BinaryWebpConverter $binaryWebpConverter,
        private readonly BinaryAvifConverter $binaryAvifConverter
    ) {
    }

    /**
     * @inheritDoc
     */
    public function convertToWebP(string $sourceImagePath, int $quality = 85, ?string $destinationPath = null): ?string
    {
        if (!file_exists($sourceImagePath) || !is_readable($sourceImagePath)) {
            $this->logger->error('Source image does not exist or is not readable', ['path' => $sourceImagePath]);
            return null;
        }

        $quality = max(1, min(100, $quality));
        $destinationPath = $destinationPath ?? $this->generateDestinationPath($sourceImagePath, 'webp');

        // Try GD extension first
        if ($this->isGdWebPSupported()) {
            $result = $this->convertToWebPWithGd($sourceImagePath, $destinationPath, $quality);
            if ($result !== null) {
                return $result;
            }
        }

        // Fallback to binary converter
        if ($this->binaryWebpConverter->isAvailable()) {
            $this->logger->info('Falling back to cwebp binary for WebP conversion');
            if ($this->binaryWebpConverter->convert($sourceImagePath, $destinationPath, $quality)) {
                return $destinationPath;
            }
        }

        $this->logger->warning('WebP conversion not supported on this server (no GD or cwebp binary available)');
        return null;
    }

    /**
     * Convert to WebP using GD extension
     *
     * @param string $sourceImagePath
     * @param string $destinationPath
     * @param int $quality
     * @return string|null
     */
    private function convertToWebPWithGd(string $sourceImagePath, string $destinationPath, int $quality): ?string
    {
        try {
            $image = $this->createImageFromFile($sourceImagePath);
            if ($image === null) {
                return null;
            }

            $this->ensureDirectoryExists(dirname($destinationPath));

            $result = imagewebp($image, $destinationPath, $quality);
            imagedestroy($image);

            if (!$result || !file_exists($destinationPath)) {
                $this->logger->error('Failed to save WebP image with GD', ['destination' => $destinationPath]);
                return null;
            }

            return $destinationPath;
        } catch (\Exception $e) {
            $this->logger->error('WebP GD conversion failed', [
                'source' => $sourceImagePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function convertToAvif(string $sourceImagePath, int $quality = 80, ?string $destinationPath = null): ?string
    {
        if (!file_exists($sourceImagePath) || !is_readable($sourceImagePath)) {
            $this->logger->error('Source image does not exist or is not readable', ['path' => $sourceImagePath]);
            return null;
        }

        $quality = max(1, min(100, $quality));
        $destinationPath = $destinationPath ?? $this->generateDestinationPath($sourceImagePath, 'avif');

        try {
            // Try Imagick first
            if ($this->isImagickAvailable() && $this->isImagickAvifSupported()) {
                $result = $this->convertToAvifWithImagick($sourceImagePath, $destinationPath, $quality);
                if ($result !== null) {
                    return $result;
                }
            }

            // Try GD extension
            if ($this->isGdAvifSupported()) {
                $result = $this->convertToAvifWithGd($sourceImagePath, $destinationPath, $quality);
                if ($result !== null) {
                    return $result;
                }
            }

            // Fallback to binary converter
            if ($this->binaryAvifConverter->isAvailable()) {
                $this->logger->info('Falling back to cavif binary for AVIF conversion');
                if ($this->binaryAvifConverter->convert($sourceImagePath, $destinationPath, $quality)) {
                    return $destinationPath;
                }
            }

            $this->logger->warning('AVIF conversion not supported on this server (no Imagick, GD, or cavif binary available)');
            return null;
        } catch (\Exception $e) {
            $this->logger->error('AVIF conversion failed', [
                'source' => $sourceImagePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function isWebPSupported(): bool
    {
        return $this->isGdWebPSupported() || $this->binaryWebpConverter->isAvailable();
    }

    /**
     * @inheritDoc
     */
    public function isAvifSupported(): bool
    {
        return $this->isGdAvifSupported()
            || ($this->isImagickAvailable() && $this->isImagickAvifSupported())
            || $this->binaryAvifConverter->isAvailable();
    }

    /**
     * @inheritDoc
     */
    public function getSupportedFormats(): array
    {
        return [
            'webp' => $this->isWebPSupported(),
            'avif' => $this->isAvifSupported(),
        ];
    }

    /**
     * Check if GD extension supports WebP
     *
     * @return bool
     */
    private function isGdWebPSupported(): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        if (!function_exists('imagewebp')) {
            return false;
        }

        $gdInfo = gd_info();
        return !empty($gdInfo['WebP Support']);
    }

    /**
     * Check if GD extension supports AVIF
     *
     * @return bool
     */
    private function isGdAvifSupported(): bool
    {
        if (!function_exists('imageavif')) {
            return false;
        }

        $gdInfo = gd_info();
        return !empty($gdInfo['AVIF Support']);
    }

    /**
     * Generate destination path with new extension
     *
     * @param string $sourcePath
     * @param string $extension
     * @return string
     */
    private function generateDestinationPath(string $sourcePath, string $extension): string
    {
        $pathInfo = pathinfo($sourcePath);
        return $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '.' . $extension;
    }

    /**
     * Create GD image resource from file
     *
     * @param string $sourcePath
     * @return \GdImage|null
     */
    private function createImageFromFile(string $sourcePath): ?\GdImage
    {
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            $this->logger->error('Could not get image info', ['path' => $sourcePath]);
            return null;
        }

        $mimeType = $imageInfo['mime'];

        return match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => $this->createFromPngWithAlpha($sourcePath),
            'image/gif' => imagecreatefromgif($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : null,
            default => null,
        };
    }

    /**
     * Create image from PNG with alpha channel preservation
     *
     * @param string $sourcePath
     * @return \GdImage|null
     */
    private function createFromPngWithAlpha(string $sourcePath): ?\GdImage
    {
        $image = imagecreatefrompng($sourcePath);
        if ($image === false) {
            return null;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * Convert to AVIF using GD
     *
     * @param string $sourcePath
     * @param string $destinationPath
     * @param int $quality
     * @return string|null
     */
    private function convertToAvifWithGd(string $sourcePath, string $destinationPath, int $quality): ?string
    {
        $image = $this->createImageFromFile($sourcePath);
        if ($image === null) {
            return null;
        }

        $this->ensureDirectoryExists(dirname($destinationPath));

        $result = imageavif($image, $destinationPath, $quality);
        imagedestroy($image);

        if (!$result || !file_exists($destinationPath)) {
            $this->logger->error('Failed to save AVIF image with GD', ['destination' => $destinationPath]);
            return null;
        }

        return $destinationPath;
    }

    /**
     * Convert to AVIF using Imagick
     *
     * @param string $sourcePath
     * @param string $destinationPath
     * @param int $quality
     * @return string|null
     */
    private function convertToAvifWithImagick(string $sourcePath, string $destinationPath, int $quality): ?string
    {
        $this->ensureDirectoryExists(dirname($destinationPath));

        $imagick = new \Imagick($sourcePath);
        $imagick->setImageFormat('avif');
        $imagick->setImageCompressionQuality($quality);

        $result = $imagick->writeImage($destinationPath);
        $imagick->destroy();

        if (!$result || !file_exists($destinationPath)) {
            $this->logger->error('Failed to save AVIF image with Imagick', ['destination' => $destinationPath]);
            return null;
        }

        return $destinationPath;
    }

    /**
     * Check if Imagick extension is available
     *
     * @return bool
     */
    private function isImagickAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists(\Imagick::class);
    }

    /**
     * Check if Imagick supports AVIF
     *
     * @return bool
     */
    private function isImagickAvifSupported(): bool
    {
        if (!$this->isImagickAvailable()) {
            return false;
        }

        try {
            $formats = \Imagick::queryFormats('AVIF');
            return !empty($formats);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure directory exists
     *
     * @param string $directory
     * @return void
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            mkdir($directory, 0775, true);
        }
    }
}
