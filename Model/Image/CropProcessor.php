<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSliderApi\Api\Image\CropProcessorInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image\AdapterFactory;
use Psr\Log\LoggerInterface;

/**
 * Image crop processor implementation
 */
class CropProcessor implements CropProcessorInterface
{
    private const ADAPTER_GD2 = 'GD2';
    private const ADAPTER_IMAGEMAGICK = 'IMAGEMAGICK';

    private ?WriteInterface $mediaDirectory = null;

    /**
     * @param AdapterFactory $adapterFactory
     * @param Filesystem $filesystem
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly AdapterFactory $adapterFactory,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function crop(
        string $sourceImagePath,
        int $cropX,
        int $cropY,
        int $cropWidth,
        int $cropHeight,
        int $targetWidth,
        int $targetHeight,
        string $destinationPath
    ): string {
        if (!$this->isAdapterAvailable()) {
            throw new LocalizedException(
                __('No image processing adapter (GD or Imagick) is available.')
            );
        }

        if (!file_exists($sourceImagePath) || !is_readable($sourceImagePath)) {
            throw new LocalizedException(
                __('Source image does not exist or is not readable: %1', $sourceImagePath)
            );
        }

        $destinationDir = dirname($destinationPath);
        if (!is_dir($destinationDir)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            if (!mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
                throw new LocalizedException(
                    __('Failed to create destination directory: %1', $destinationDir)
                );
            }
        }

        try {
            $adapter = $this->createAdapter();
            $adapter->open($sourceImagePath);

            $adapter->crop($cropY, $cropX, $adapter->getOriginalWidth() - $cropX - $cropWidth, $adapter->getOriginalHeight() - $cropY - $cropHeight);

            $adapter->resize($targetWidth, $targetHeight);

            $adapter->save($destinationPath);

            return $destinationPath;
        } catch (\Exception $e) {
            $this->logger->error(
                'Image cropping failed',
                [
                    'source' => $sourceImagePath,
                    'destination' => $destinationPath,
                    'error' => $e->getMessage(),
                ]
            );

            throw new LocalizedException(
                __('Failed to crop image: %1', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function isAdapterAvailable(): bool
    {
        return $this->isImagickAvailable() || $this->isGdAvailable();
    }

    /**
     * @inheritDoc
     */
    public function getAvailableAdapterName(): ?string
    {
        if ($this->isImagickAvailable()) {
            return 'Imagick';
        }

        if ($this->isGdAvailable()) {
            return 'GD';
        }

        return null;
    }

    /**
     * Create appropriate image adapter
     *
     * @return \Magento\Framework\Image\Adapter\AdapterInterface
     */
    private function createAdapter(): \Magento\Framework\Image\Adapter\AdapterInterface
    {
        if ($this->isImagickAvailable()) {
            return $this->adapterFactory->create(self::ADAPTER_IMAGEMAGICK);
        }

        return $this->adapterFactory->create(self::ADAPTER_GD2);
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
     * Check if GD extension is available
     *
     * @return bool
     */
    private function isGdAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /**
     * Get media directory instance
     *
     * @return WriteInterface
     * @throws FileSystemException
     */
    private function getMediaDirectory(): WriteInterface
    {
        if ($this->mediaDirectory === null) {
            $this->mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        }

        return $this->mediaDirectory;
    }
}
