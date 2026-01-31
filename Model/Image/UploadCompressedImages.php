<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImagePathConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ResponsiveImageGeneratorInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\UploadCompressedImagesInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for uploading pre-compressed images from browser
 */
class UploadCompressedImages implements UploadCompressedImagesInterface
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    private const MAX_FILE_SIZE = 10485760;

    private ?WriteInterface $mediaDirectory = null;

    /**
     * @param ResponsiveCropRepositoryInterface $responsiveCropRepository
     * @param BreakpointRepositoryInterface $breakpointRepository
     * @param ResponsiveImageGeneratorInterface $imageGenerator
     * @param Filesystem $filesystem
     * @param LoggerInterface $logger
     * @param ImagePathConfigInterface $imagePathConfig
     */
    public function __construct(
        private readonly ResponsiveCropRepositoryInterface $responsiveCropRepository,
        private readonly BreakpointRepositoryInterface $breakpointRepository,
        private readonly ResponsiveImageGeneratorInterface $imageGenerator,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        private readonly ImagePathConfigInterface $imagePathConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function upload(int $cropId, array $files, array $params = []): ResponsiveCropInterface
    {
        $crop = $this->responsiveCropRepository->getById($cropId);
        $breakpoint = $this->breakpointRepository->getById($crop->getBreakpointId());

        $this->imageGenerator->deleteGeneratedImages($crop);

        $hash = $this->generateImageHash($crop, $params);
        $bannerId = $crop->getBannerId();
        $breakpointIdentifier = $breakpoint->getIdentifier();

        if (isset($files['cropped_image']) && !empty($files['cropped_image']['tmp_name'])) {
            $croppedPath = $this->storeCompressedImage(
                $bannerId,
                $breakpointIdentifier . '_' . $hash,
                'original',
                $files['cropped_image']['tmp_name'],
                $files['cropped_image']['type'] ?? 'image/jpeg'
            );
            $crop->setCroppedImage($croppedPath);
        }

        if (isset($files['webp_image']) && !empty($files['webp_image']['tmp_name'])) {
            $webpPath = $this->storeCompressedImage(
                $bannerId,
                $breakpointIdentifier . '_' . $hash,
                'webp',
                $files['webp_image']['tmp_name'],
                'image/webp'
            );
            $crop->setWebpImage($webpPath);
        }

        if (isset($files['avif_image']) && !empty($files['avif_image']['tmp_name'])) {
            $avifPath = $this->storeCompressedImage(
                $bannerId,
                $breakpointIdentifier . '_' . $hash,
                'avif',
                $files['avif_image']['tmp_name'],
                'image/avif'
            );
            $crop->setAvifImage($avifPath);
        }

        if (isset($params['crop_x'])) {
            $crop->setCropX((int)$params['crop_x']);
        }
        if (isset($params['crop_y'])) {
            $crop->setCropY((int)$params['crop_y']);
        }
        if (isset($params['crop_width'])) {
            $crop->setCropWidth((int)$params['crop_width']);
        }
        if (isset($params['crop_height'])) {
            $crop->setCropHeight((int)$params['crop_height']);
        }
        if (isset($params['webp_quality'])) {
            $crop->setWebpQuality((int)$params['webp_quality']);
        }
        if (isset($params['avif_quality'])) {
            $crop->setAvifQuality((int)$params['avif_quality']);
        }

        $crop->setSortOrder($breakpoint->getSortOrder());

        return $this->responsiveCropRepository->save($crop);
    }

    /**
     * @inheritDoc
     */
    public function storeCompressedImage(
        int $bannerId,
        string $breakpointIdentifier,
        string $format,
        string $tmpFilePath,
        string $mimeType
    ): string {
        $this->validateUploadedFile($tmpFilePath, $mimeType);

        $extension = $this->getExtensionFromMimeType($mimeType, $format);
        $fileName = $breakpointIdentifier . '.' . $extension;
        $relativePath = $this->imagePathConfig->getResponsivePath() . '/' . $bannerId . '/' . $fileName;

        $mediaDirectory = $this->getMediaDirectory();
        $absolutePath = $mediaDirectory->getAbsolutePath($relativePath);

        $this->ensureDirectoryExists(dirname($absolutePath));

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        if (!copy($tmpFilePath, $absolutePath)) {
            throw new LocalizedException(__('Failed to save uploaded image.'));
        }

        return $relativePath;
    }

    /**
     * @inheritDoc
     */
    public function validateUploadedFile(string $tmpFilePath, string $mimeType): bool
    {
        if (!file_exists($tmpFilePath)) {
            throw new LocalizedException(__('Uploaded file does not exist.'));
        }

        if (!is_readable($tmpFilePath)) {
            throw new LocalizedException(__('Uploaded file is not readable.'));
        }

        $fileSize = filesize($tmpFilePath);
        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE) {
            throw new LocalizedException(
                __('File size exceeds maximum allowed size of %1 MB.', self::MAX_FILE_SIZE / 1048576)
            );
        }

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new LocalizedException(
                __('Invalid file type. Allowed types: %1', implode(', ', array_keys(self::ALLOWED_MIME_TYPES)))
            );
        }

        return true;
    }

    /**
     * Get file extension from MIME type
     *
     * @param string $mimeType
     * @param string $format
     * @return string
     */
    private function getExtensionFromMimeType(string $mimeType, string $format): string
    {
        if ($format === 'webp') {
            return 'webp';
        }
        if ($format === 'avif') {
            return 'avif';
        }

        return self::ALLOWED_MIME_TYPES[$mimeType] ?? 'jpg';
    }

    /**
     * Generate hash for image based on crop parameters
     *
     * @param ResponsiveCropInterface $crop
     * @param array<string, mixed> $params
     * @return string
     */
    private function generateImageHash(ResponsiveCropInterface $crop, array $params): string
    {
        $data = [
            $crop->getSourceImage(),
            $params['crop_x'] ?? $crop->getCropX(),
            $params['crop_y'] ?? $crop->getCropY(),
            $params['crop_width'] ?? $crop->getCropWidth(),
            $params['crop_height'] ?? $crop->getCropHeight(),
            $params['webp_quality'] ?? $crop->getWebpQuality(),
            $params['avif_quality'] ?? $crop->getAvifQuality(),
            time(),
        ];

        return substr(md5(implode('_', array_map('strval', $data))), 0, 8);
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

    /**
     * Get media directory instance
     *
     * @return WriteInterface
     */
    private function getMediaDirectory(): WriteInterface
    {
        if ($this->mediaDirectory === null) {
            $this->mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        }

        return $this->mediaDirectory;
    }
}
