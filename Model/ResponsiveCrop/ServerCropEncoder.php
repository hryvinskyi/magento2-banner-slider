<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\CropRenderer;
use Hryvinskyi\BannerSlider\Model\Image\ImageConverter;
use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\FormatRequest;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use Psr\Log\LoggerInterface;

/**
 * Renders a crop on the server and encodes it into the formats the browser did not supply.
 *
 * The source is cut and scaled at most twice: once in the original's format when that output is needed, and once in a
 * lossless intermediate format that every variant is encoded from, so a variant never inherits JPEG artefacts. When
 * the original is itself in the lossless format, its render serves the variants too. Temp files are always removed.
 */
class ServerCropEncoder
{
    /**
     * @param CropRenderer $cropRenderer
     * @param ImageConverter $imageConverter
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param LocalFileWorkspace $workspace
     * @param LocalFileDriver $localDriver
     * @param LoggerInterface $logger
     * @param string $intermediateFormatCode Lossless format the variants are encoded from
     */
    public function __construct(
        private readonly CropRenderer $cropRenderer,
        private readonly ImageConverter $imageConverter,
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly LocalFileWorkspace $workspace,
        private readonly LocalFileDriver $localDriver,
        private readonly LoggerInterface $logger,
        private readonly string $intermediateFormatCode = 'png'
    ) {
    }

    /**
     * Encoded bytes of the crop per format code
     *
     * @param MediaImage $source
     * @param CropRect $rect
     * @param Dimensions $target
     * @param ImageFormat|null $original The original-format output to render, or null when it is not needed
     * @param list<FormatRequest> $variants Variant formats to encode, with their quality
     * @return array<string,string> Bytes by format code
     * @throws LocalizedException When the crop cannot be rendered or a format cannot be encoded; the message is safe
     *     to show an admin
     * @throws \InvalidArgumentException When a variant or the intermediate format is not registered
     */
    public function encode(
        MediaImage $source,
        CropRect $rect,
        Dimensions $target,
        ?ImageFormat $original,
        array $variants
    ): array {
        $temps = [];
        $bytes = [];
        try {
            $base = null;
            if ($original !== null) {
                $originalLocal = $temps[] = $this->render($source, $rect, $target, $original);
                $bytes[$original->getCode()] = $this->localDriver->fileGetContents($originalLocal);
                $base = $original->getCode() === $this->intermediateFormatCode ? $originalLocal : null;
            }
            if ($variants === []) {
                return $bytes;
            }

            $base ??= $temps[] = $this->render(
                $source,
                $rect,
                $target,
                $this->formatRegistry->get($this->intermediateFormatCode)
            );
            foreach ($variants as $request) {
                $format = $this->formatRegistry->get($request->getFormatCode());
                $output = $temps[] = $this->workspace->newTempPath($format->getExtension());
                $this->imageConverter->convert($base, $format, $request->getQuality(), $output);
                $bytes[$format->getCode()] = $this->localDriver->fileGetContents($output);
            }

            return $bytes;
        } finally {
            $this->discardAll($temps);
        }
    }

    /**
     * Render the crop into a local temp file of the format
     *
     * @param MediaImage $source
     * @param CropRect $rect
     * @param Dimensions $target
     * @param ImageFormat $format
     * @return string Absolute local path
     * @throws LocalizedException
     */
    private function render(MediaImage $source, CropRect $rect, Dimensions $target, ImageFormat $format): string
    {
        return $this->cropRenderer->render(
            $source->getPath(),
            $rect,
            $target->getWidth(),
            $target->getHeight(),
            $format
        );
    }

    /**
     * Remove temp files; a failure is logged, never thrown, so it cannot hide the result or the original error
     *
     * @param list<string> $localPaths
     * @return void
     */
    private function discardAll(array $localPaths): void
    {
        foreach ($localPaths as $localPath) {
            try {
                $this->workspace->discard($localPath);
            } catch (\Throwable $exception) {
                $this->logger->warning(
                    'Banner slider: a temp file of a crop render could not be removed.',
                    ['exception' => $exception]
                );
            }
        }
    }
}
