<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\CropProcessorInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\FormatConverterInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImagePathConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ResponsiveImageGeneratorInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Responsive image generator implementation
 */
class ResponsiveImageGenerator implements ResponsiveImageGeneratorInterface
{
    private ?WriteInterface $mediaDirectory = null;

    /**
     * @param CropProcessorInterface $cropProcessor
     * @param FormatConverterInterface $formatConverter
     * @param ResponsiveCropRepositoryInterface $responsiveCropRepository
     * @param BreakpointRepositoryInterface $breakpointRepository
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ImagePathConfigInterface $imagePathConfig
     */
    public function __construct(
        private readonly CropProcessorInterface $cropProcessor,
        private readonly FormatConverterInterface $formatConverter,
        private readonly ResponsiveCropRepositoryInterface $responsiveCropRepository,
        private readonly BreakpointRepositoryInterface $breakpointRepository,
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly ImagePathConfigInterface $imagePathConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function generate(ResponsiveCropInterface $crop, BreakpointInterface $breakpoint): ResponsiveCropInterface
    {
        if (!$crop->getSourceImage()) {
            throw new LocalizedException(__('No source image specified for crop.'));
        }

        $sourceImagePath = $this->getAbsoluteMediaPath($crop->getSourceImage());

        if (!file_exists($sourceImagePath)) {
            throw new LocalizedException(__('Source image does not exist: %1', $crop->getSourceImage()));
        }

        $bannerId = $crop->getBannerId();
        $breakpointIdentifier = $breakpoint->getIdentifier();
        $hash = $this->generateImageHash($crop);

        $baseName = sprintf('%d/%s_%s', $bannerId, $breakpointIdentifier, $hash);
        $baseOutputPath = $this->getResponsiveImageBasePath() . DIRECTORY_SEPARATOR . $baseName;

        $this->deleteGeneratedImages($crop);

        $croppedImagePath = $baseOutputPath . $this->getSourceExtension($sourceImagePath);

        $targetWidth = $breakpoint->getTargetWidth();
        $targetHeight = $breakpoint->getTargetHeight();
        $cropWidth = $crop->getCropWidth() ?? $targetWidth;
        $cropHeight = $crop->getCropHeight() ?? $targetHeight;

        // Always calculate target height based on crop aspect ratio to avoid image stretching.
        // This ensures the cropped area is resized proportionally to fit target width.
        if ($cropWidth > 0 && $cropHeight > 0) {
            $targetHeight = (int) round($targetWidth * $cropHeight / $cropWidth);
        }

        $this->cropProcessor->crop(
            $sourceImagePath,
            $crop->getCropX() ?? 0,
            $crop->getCropY() ?? 0,
            $cropWidth,
            $cropHeight,
            $targetWidth,
            $targetHeight,
            $croppedImagePath
        );

        $crop->setCroppedImage($this->getRelativeMediaPath($croppedImagePath));

        if ($crop->isGenerateWebpEnabled() && $this->formatConverter->isWebPSupported()) {
            $webpPath = $this->formatConverter->convertToWebP(
                $croppedImagePath,
                $crop->getWebpQuality() ?? 85
            );

            if ($webpPath !== null) {
                $crop->setWebpImage($this->getRelativeMediaPath($webpPath));
            }
        }

        if ($crop->isGenerateAvifEnabled() && $this->formatConverter->isAvifSupported()) {
            $avifPath = $this->formatConverter->convertToAvif(
                $croppedImagePath,
                $crop->getAvifQuality() ?? 80
            );

            if ($avifPath !== null) {
                $crop->setAvifImage($this->getRelativeMediaPath($avifPath));
            }
        }

        $crop->setSortOrder($breakpoint->getSortOrder());

        return $crop;
    }

    /**
     * @inheritDoc
     */
    public function generateForBanner(int $bannerId): array
    {
        $crops = $this->responsiveCropRepository->getByBannerId($bannerId);
        $generatedCrops = [];

        foreach ($crops as $crop) {
            if (!$crop->getSourceImage()) {
                continue;
            }

            try {
                $breakpoint = $this->breakpointRepository->getById($crop->getBreakpointId());
                $generatedCrop = $this->generate($crop, $breakpoint);
                $this->responsiveCropRepository->save($generatedCrop);
                $generatedCrops[] = $generatedCrop;
            } catch (\Exception $e) {
                $this->logger->error('Failed to generate responsive image', [
                    'banner_id' => $bannerId,
                    'crop_id' => $crop->getCropId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $generatedCrops;
    }

    /**
     * @inheritDoc
     */
    public function deleteGeneratedImages(ResponsiveCropInterface $crop): bool
    {
        $deleted = true;
        $mediaDirectory = $this->getMediaDirectory();

        $imagePaths = [
            $crop->getCroppedImage(),
            $crop->getWebpImage(),
            $crop->getAvifImage(),
        ];

        foreach ($imagePaths as $imagePath) {
            if ($imagePath === null || $imagePath === '') {
                continue;
            }

            try {
                if ($mediaDirectory->isExist($imagePath)) {
                    $mediaDirectory->delete($imagePath);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to delete generated image', [
                    'path' => $imagePath,
                    'error' => $e->getMessage(),
                ]);
                $deleted = false;
            }
        }

        $crop->setCroppedImage(null);
        $crop->setWebpImage(null);
        $crop->setAvifImage(null);

        return $deleted;
    }

    /**
     * @inheritDoc
     */
    public function getResponsiveImageBasePath(): string
    {
        return $this->getMediaDirectory()->getAbsolutePath($this->imagePathConfig->getResponsivePath());
    }

    /**
     * @inheritDoc
     */
    public function getResponsiveImageBaseUrl(): string
    {
        try {
            return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $this->imagePathConfig->getResponsivePath();
        } catch (\Exception $e) {
            $this->logger->error('Failed to get base URL', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Get absolute path to media file
     *
     * @param string $relativePath
     * @return string
     */
    private function getAbsoluteMediaPath(string $relativePath): string
    {
        return $this->getMediaDirectory()->getAbsolutePath($relativePath);
    }

    /**
     * Get relative path from absolute media path
     *
     * @param string $absolutePath
     * @return string
     */
    private function getRelativeMediaPath(string $absolutePath): string
    {
        $mediaPath = $this->getMediaDirectory()->getAbsolutePath();
        if (str_starts_with($absolutePath, $mediaPath)) {
            return substr($absolutePath, strlen($mediaPath));
        }

        return $absolutePath;
    }

    /**
     * Generate hash for image based on crop parameters
     *
     * @param ResponsiveCropInterface $crop
     * @return string
     */
    private function generateImageHash(ResponsiveCropInterface $crop): string
    {
        $data = [
            $crop->getSourceImage(),
            $crop->getCropX(),
            $crop->getCropY(),
            $crop->getCropWidth(),
            $crop->getCropHeight(),
            $crop->getWebpQuality(),
            $crop->getAvifQuality(),
        ];

        return substr(md5(implode('_', array_map('strval', $data))), 0, 8);
    }

    /**
     * Get source file extension
     *
     * @param string $sourcePath
     * @return string
     */
    private function getSourceExtension(string $sourcePath): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        return $extension ? '.' . $extension : '.jpg';
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
