<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Image\Adapter\AbstractAdapter;
use Magento\Framework\Image\AdapterFactory;

/**
 * Cuts a rectangle out of a media image and scales it to a breakpoint's target size, into a local temp file.
 *
 * - The rectangle must lie inside the source image, whose size is read from the file itself.
 * - Without a target height the height follows the rectangle's aspect ratio.
 * - The output is written in one of the formats the image adapter writes (`di.xml`; JPEG and PNG). A source in
 *   another format (GIF, WebP, AVIF) is first re-encoded into the output format without loss of size, so the adapter
 *   always opens a format it supports.
 * - An output format listed as a quality format (`di.xml`; JPEG) is saved at the configured quality of that format
 *   (the framework's image adapters take a quality); the others (PNG, lossless) at the adapter's own setting.
 * - A source or an output with more pixels than the image pixel limit is refused before anything is decoded.
 *
 * Messages never contain a server path; adapter details travel in the previous exception, for the log.
 */
class CropRenderer
{
    private const INTERMEDIATE_QUALITY = 100;

    /**
     * @param LocalFileWorkspace $workspace
     * @param ImageInspector $imageInspector
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param ImageConverter $imageConverter
     * @param AdapterFactory $adapterFactory
     * @param ImagePixelLimit $pixelLimit
     * @param ImageConfigInterface $imageConfig
     * @param array<string> $outputFormats Codes of the formats the image adapter writes
     * @param array<string> $qualityFormats Codes of the output formats saved at their configured quality
     */
    public function __construct(
        private readonly LocalFileWorkspace $workspace,
        private readonly ImageInspector $imageInspector,
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly ImageConverter $imageConverter,
        private readonly AdapterFactory $adapterFactory,
        private readonly ImagePixelLimit $pixelLimit,
        private readonly ImageConfigInterface $imageConfig,
        private readonly array $outputFormats = ['jpeg', 'png'],
        private readonly array $qualityFormats = ['jpeg']
    ) {
    }

    /**
     * Render the crop and return the absolute path of the local temp file holding it
     *
     * The caller owns the returned file and removes it with LocalFileWorkspace::discard().
     *
     * @param string $sourceMediaPath Safe path of the source image, relative to the media directory
     * @param CropRect $rect Rectangle in source pixels
     * @param int $targetWidth Output width in pixels, > 0
     * @param int|null $targetHeight Output height in pixels, > 0, or null to keep the rectangle's aspect ratio
     * @param ImageFormat $outputFormat
     * @return string
     * @throws LocalizedException When the source is not a readable image, the rectangle does not fit it, the target
     *     size or output format is not supported, or the crop cannot be rendered
     * @throws EncodingException When the source or the output has more pixels than the image pixel limit
     */
    public function render(
        string $sourceMediaPath,
        CropRect $rect,
        int $targetWidth,
        ?int $targetHeight,
        ImageFormat $outputFormat
    ): string {
        if (!in_array($outputFormat->getCode(), $this->outputFormats, true)) {
            throw new LocalizedException(__(
                'A crop cannot be rendered as %1; use one of: %2.',
                $outputFormat->getCode(),
                implode(', ', $this->outputFormats)
            ));
        }
        $target = $this->targetSize($rect, $targetWidth, $targetHeight);
        $this->pixelLimit->assertProcessable($target);

        return $this->workspace->withLocalCopy(
            $sourceMediaPath,
            fn (string $localSource): string => $this->renderLocal($localSource, $rect, $target, $outputFormat)
        );
    }

    /**
     * Render from a local copy of the source
     *
     * @param string $localSource
     * @param CropRect $rect
     * @param Dimensions $target
     * @param ImageFormat $outputFormat
     * @return string
     * @throws LocalizedException
     */
    private function renderLocal(
        string $localSource,
        CropRect $rect,
        Dimensions $target,
        ImageFormat $outputFormat
    ): string {
        $info = $this->imageInspector->inspect($localSource);
        $sourceFormat = $info === null ? null : $this->formatRegistry->getByMimeType($info['mime']);
        if ($info === null || $sourceFormat === null) {
            throw new LocalizedException(__('The crop source is not an image in a supported format.'));
        }
        $source = new Dimensions($info['width'], $info['height']);
        $this->pixelLimit->assertProcessable($source);
        if (!$rect->fitsWithin($source)) {
            throw new LocalizedException(__(
                'The crop area %1x%2 at %3,%4 for the %5x%6 breakpoint does not fit inside the %7x%8 source image.',
                $rect->getWidth(),
                $rect->getHeight(),
                $rect->getX(),
                $rect->getY(),
                $target->getWidth(),
                $target->getHeight(),
                $source->getWidth(),
                $source->getHeight()
            ));
        }

        $intermediate = null;
        $rendered = false;
        $output = $this->workspace->newTempPath($outputFormat->getExtension());
        try {
            $adapterSource = $localSource;
            if (!$sourceFormat->equals($outputFormat)) {
                $intermediate = $this->workspace->newTempPath($outputFormat->getExtension());
                $this->imageConverter->convert($localSource, $outputFormat, self::INTERMEDIATE_QUALITY, $intermediate);
                $adapterSource = $intermediate;
            }
            $this->cropAndScale($adapterSource, $rect, $source, $target, $outputFormat, $output);
            $rendered = true;
        } catch (\Exception $e) {
            throw new LocalizedException(
                __(
                    'The crop for the %1x%2 breakpoint could not be rendered.',
                    $target->getWidth(),
                    $target->getHeight()
                ),
                $e
            );
        } finally {
            if ($intermediate !== null) {
                $this->workspace->discard($intermediate);
            }
            if (!$rendered) {
                $this->workspace->discard($output);
            }
        }

        return $output;
    }

    /**
     * Crop and scale through the configured image adapter
     *
     * @param string $adapterSource Local image in the output format
     * @param CropRect $rect
     * @param Dimensions $source
     * @param Dimensions $target
     * @param ImageFormat $outputFormat
     * @param string $output
     * @return void
     * @throws \Exception When the adapter fails
     */
    private function cropAndScale(
        string $adapterSource,
        CropRect $rect,
        Dimensions $source,
        Dimensions $target,
        ImageFormat $outputFormat,
        string $output
    ): void {
        $adapter = $this->adapterFactory->create();
        if ($adapter instanceof AbstractAdapter && in_array($outputFormat->getCode(), $this->qualityFormats, true)) {
            $adapter->quality($this->imageConfig->getDefaultQuality($outputFormat->getCode()));
        }
        $adapter->open($adapterSource);
        $adapter->crop(
            $rect->getY(),
            $rect->getX(),
            $source->getWidth() - $rect->getX() - $rect->getWidth(),
            $source->getHeight() - $rect->getY() - $rect->getHeight()
        );
        $adapter->resize($target->getWidth(), $target->getHeight());
        $adapter->save($output);
    }

    /**
     * The output size: the target width, and the target height or one derived from the rectangle
     *
     * @param CropRect $rect
     * @param int $targetWidth
     * @param int|null $targetHeight
     * @return Dimensions
     * @throws LocalizedException When a target side is not greater than zero
     */
    private function targetSize(CropRect $rect, int $targetWidth, ?int $targetHeight): Dimensions
    {
        if ($targetWidth < 1 || ($targetHeight !== null && $targetHeight < 1)) {
            throw new LocalizedException(__('The breakpoint target width and height must be greater than 0.'));
        }
        $height = $targetHeight ?? max(1, (int)round($targetWidth * $rect->getHeight() / $rect->getWidth()));

        return new Dimensions($targetWidth, $height);
    }
}
