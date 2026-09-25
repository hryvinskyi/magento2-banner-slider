<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Magento\Framework\Exception\LocalizedException;

/**
 * Reads what a media file really is: its pixel size and its registered format, both taken from the file's bytes.
 *
 * Any safe media path may be read, inside the package folders or not. The file is inspected through a local copy, so
 * remote media storage works. A file that is missing, not an image, or in a format the registry does not know fails
 * with a message fit for an admin, and so does an image with more pixels than the image pixel limit: nothing is cut,
 * sized or rendered from it.
 */
class MediaImageReader
{
    /**
     * @param LocalFileWorkspace $workspace
     * @param ImageInspector $imageInspector
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param ImagePixelLimit $pixelLimit
     */
    public function __construct(
        private readonly LocalFileWorkspace $workspace,
        private readonly ImageInspector $imageInspector,
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly ImagePixelLimit $pixelLimit
    ) {
    }

    /**
     * The size and format of a media image
     *
     * @param string $mediaPath Path relative to the media directory
     * @return MediaImage
     * @throws LocalizedException When the file cannot be read, is not an image, or its format is not registered
     * @throws EncodingException When the image has more pixels than the image pixel limit
     */
    public function read(string $mediaPath): MediaImage
    {
        try {
            $info = $this->workspace->withLocalCopy(
                $mediaPath,
                fn (string $localPath): ?array => $this->imageInspector->inspect($localPath)
            );
        } catch (LocalizedException | \InvalidArgumentException $exception) {
            throw new LocalizedException(__('The image "%1" cannot be read.', $mediaPath), $exception);
        }
        if ($info === null) {
            throw new LocalizedException(__('The file "%1" is not a readable image.', $mediaPath));
        }
        $format = $this->formatRegistry->getByMimeType($info['mime']);
        if ($format === null) {
            throw new LocalizedException(
                __('The image "%1" is in an unsupported format (%2).', $mediaPath, $info['mime'])
            );
        }

        $dimensions = new Dimensions($info['width'], $info['height']);
        if ($this->pixelLimit->isExceededBy($dimensions)) {
            throw new EncodingException(__(
                'The image "%1" is %2x%3 pixels, more than the %4 pixels images may have here.',
                $mediaPath,
                $dimensions->getWidth(),
                $dimensions->getHeight(),
                $this->pixelLimit->getMaxPixels()
            ));
        }

        return new MediaImage($mediaPath, $dimensions, $format);
    }
}
