<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Hryvinskyi\BannerSlider\Model\Image\ImagePixelLimit;
use Hryvinskyi\BannerSlider\Model\Image\JpegOrientationNormaliser;
use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\ImageUploadInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\StoredMedia;
use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Phrase;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;

/**
 * Stores banner images under the package's `image` media root.
 *
 * The accepted types are the registered formats listed in `di.xml`; a listed code the registry does not know is
 * ignored, so taking a format out of the registry also stops its uploads. The file must also decode as an image of
 * the sniffed type and have no more pixels than the image pixel limit. A JPEG stored rotated or mirrored (by its EXIF
 * orientation) is turned upright before it is stored, and the pixel size returned with the stored path is the upright
 * one.
 */
class ImageUpload implements ImageUploadInterface
{
    private const ROOT_PURPOSE = 'image';

    /**
     * @var list<string>
     */
    private readonly array $allowedFormats;

    /**
     * @param UploadedFileValidator $validator
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param ImageConfigInterface $imageConfig
     * @param ImageInspector $imageInspector
     * @param UploadedFileStore $fileStore
     * @param MediaPaths $mediaPaths
     * @param ImagePixelLimit $pixelLimit
     * @param JpegOrientationNormaliser $orientationNormaliser
     * @param array<array-key,string> $allowedFormats Codes of the registered formats an upload may have
     */
    public function __construct(
        private readonly UploadedFileValidator $validator,
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly ImageConfigInterface $imageConfig,
        private readonly ImageInspector $imageInspector,
        private readonly UploadedFileStore $fileStore,
        private readonly MediaPaths $mediaPaths,
        private readonly ImagePixelLimit $pixelLimit,
        private readonly JpegOrientationNormaliser $orientationNormaliser,
        array $allowedFormats = []
    ) {
        $this->allowedFormats = array_values($allowedFormats);
    }

    /**
     * @inheritDoc
     */
    public function upload(UploadedFile $file): StoredMedia
    {
        $accepted = $this->validator->validate(
            $file,
            $this->allowedTypes(),
            $this->imageConfig->getMaxUploadBytes()
        );
        $dimensions = $this->dimensionsOf($file, $accepted['mime']);
        if ($this->pixelLimit->isExceededBy($dimensions)) {
            throw $this->invalid(__(
                'The image is %1x%2 pixels; images may have at most %3 pixels.',
                $dimensions->getWidth(),
                $dimensions->getHeight(),
                $this->pixelLimit->getMaxPixels()
            ));
        }

        try {
            return $this->orientationNormaliser->withUpright(
                $file,
                $accepted['mime'],
                fn (UploadedFile $upright): StoredMedia => new StoredMedia(
                    $this->fileStore->store(
                        $upright,
                        $this->mediaPaths->getRoot(self::ROOT_PURPOSE),
                        $accepted['extension']
                    ),
                    $upright === $file ? $dimensions : $this->dimensionsOf($upright, $accepted['mime']),
                    $accepted['mime'],
                    $upright === $file ? $accepted['size'] : $upright->getSize()
                )
            );
        } catch (EncodingException $exception) {
            throw $this->invalid(__('The file is not a readable image.'), $exception);
        } catch (FileSystemException | ValidatorException $exception) {
            throw new CouldNotSaveException(__('The uploaded file could not be stored.'), $exception);
        }
    }

    /**
     * The pixel size of a received file that must be an image of the sniffed type
     *
     * @param UploadedFile $file
     * @param string $mimeType
     * @return Dimensions
     * @throws ValidationException When the file is not a readable image of that type
     */
    private function dimensionsOf(UploadedFile $file, string $mimeType): Dimensions
    {
        $image = $this->imageInspector->inspect($file->getTemporaryPath());
        if ($image === null || $image['mime'] !== $mimeType) {
            throw $this->invalid(__('The file is not a readable image.'));
        }

        return new Dimensions($image['width'], $image['height']);
    }

    /**
     * The accepted MIME types with the extension a stored file of each type gets
     *
     * @return array<string,string>
     */
    private function allowedTypes(): array
    {
        $types = [];
        foreach ($this->allowedFormats as $code) {
            if (!$this->formatRegistry->has($code)) {
                continue;
            }
            $format = $this->formatRegistry->get($code);
            $types[$format->getMimeType()] = $format->getExtension();
        }

        return $types;
    }

    /**
     * A validation failure carrying one message
     *
     * @param Phrase $message
     * @param \Exception|null $cause
     * @return ValidationException
     */
    private function invalid(Phrase $message, ?\Exception $cause = null): ValidationException
    {
        return new ValidationException($message, $cause, 0, new ValidationResult([$message]));
    }
}
