<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Picture;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\CollectionFactory as BreakpointCollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\Collection as CropCollection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory as CropCollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Picture\PictureSourcesProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFile;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Psr\Log\LoggerInterface;

/**
 * Builds the `<picture>` sources of banners from their crops, reading the crops (with their variants, loaded in bulk)
 * together with each banner's slider in one query and the enabled breakpoints of those sliders in another.
 *
 * A crop becomes a source only when it is enabled, its original-format output exists, and its breakpoint is enabled
 * and belongs to the banner's current slider; a crop left from a slider the banner moved away from never renders.
 * Sources come in the breakpoints' rendering order (widest first), whatever order the crops were stored in.
 *
 * The images of a source are the generated variants in the registry's preference order, then the original-format
 * output, whose format comes from its file extension. Stored paths are used as they are, wherever in media they
 * point. A crop whose original has an unknown extension, or whose rendered size cannot be known (no rectangle and a
 * breakpoint without a fixed height, or a breakpoint without a target width), is left out and logged.
 */
class PictureSourcesProvider implements PictureSourcesProviderInterface
{
    /**
     * @param CropCollectionFactory $cropCollectionFactory
     * @param BreakpointCollectionFactory $breakpointCollectionFactory
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param CropTargetSize $cropTargetSize
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CropCollectionFactory $cropCollectionFactory,
        private readonly BreakpointCollectionFactory $breakpointCollectionFactory,
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly CropTargetSize $cropTargetSize,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getForBanners(array $bannerIds): array
    {
        $result = array_fill_keys($bannerIds, []);
        $validIds = array_values(array_unique(array_filter($bannerIds, fn (int $id): bool => $id > 0)));
        if ($validIds === []) {
            return $result;
        }

        $crops = $this->loadCrops($validIds);
        $breakpoints = $this->loadBreakpoints(array_values(array_unique(array_column($crops, 'sliderId'))));
        $variantPreference = $this->variantPreference();

        $ranked = [];
        foreach ($crops as ['crop' => $crop, 'sliderId' => $sliderId]) {
            $bannerId = $crop->getBannerId();
            $breakpoint = $breakpoints[(int)$crop->getBreakpointId()] ?? null;
            if ($bannerId === null || $breakpoint === null || $breakpoint['breakpoint']->getSliderId() !== $sliderId) {
                continue;
            }
            $source = $this->buildSource($crop, $breakpoint['breakpoint'], $variantPreference);
            if ($source !== null) {
                $ranked[$bannerId][$breakpoint['rank']] = $source;
            }
        }

        foreach ($ranked as $bannerId => $sources) {
            ksort($sources);
            $result[$bannerId] = array_values($sources);
        }

        return $result;
    }

    /**
     * Enabled crops with a generated original, each with the slider id of its banner
     *
     * @param non-empty-list<int> $bannerIds
     * @return list<array{crop: ResponsiveCropInterface, sliderId: int}>
     */
    private function loadCrops(array $bannerIds): array
    {
        $collection = $this->cropCollectionFactory->create()
            ->addBannerIdsFilter($bannerIds)
            ->addEnabledFilter()
            ->addGeneratedFilter()
            ->joinBannerSliderId();

        $crops = [];
        foreach ($collection->getItems() as $item) {
            if (!$item instanceof ResponsiveCropInterface) {
                continue;
            }
            $sliderId = $item->getData(CropCollection::BANNER_SLIDER_ID);
            if (is_numeric($sliderId) && (int)$sliderId > 0) {
                $crops[] = ['crop' => $item, 'sliderId' => (int)$sliderId];
            }
        }

        return $crops;
    }

    /**
     * Enabled breakpoints of the sliders by id, each with its position in rendering order
     *
     * @param list<int> $sliderIds
     * @return array<int, array{breakpoint: BreakpointInterface, rank: int}>
     */
    private function loadBreakpoints(array $sliderIds): array
    {
        if ($sliderIds === []) {
            return [];
        }

        $collection = $this->breakpointCollectionFactory->create()
            ->addSliderIdsFilter($sliderIds)
            ->addEnabledFilter()
            ->orderForRendering();

        $breakpoints = [];
        $rank = 0;
        foreach ($collection->getItems() as $item) {
            $breakpointId = $item instanceof BreakpointInterface ? $item->getBreakpointId() : null;
            if ($breakpointId !== null) {
                $breakpoints[$breakpointId] = ['breakpoint' => $item, 'rank' => $rank++];
            }
        }

        return $breakpoints;
    }

    /**
     * Position of each variant format code in the registry's preference order
     *
     * @return array<string,int>
     */
    private function variantPreference(): array
    {
        $preference = [];
        foreach ($this->formatRegistry->getVariantFormats() as $position => $format) {
            $preference[$format->getCode()] = $position;
        }

        return $preference;
    }

    /**
     * The picture source of a crop, or null when it cannot be rendered
     *
     * @param ResponsiveCropInterface $crop
     * @param BreakpointInterface $breakpoint
     * @param array<string,int> $variantPreference
     * @return PictureSource|null
     */
    private function buildSource(
        ResponsiveCropInterface $crop,
        BreakpointInterface $breakpoint,
        array $variantPreference
    ): ?PictureSource {
        $original = $crop->getCroppedImage();
        if ($original === null) {
            return null;
        }
        $originalFormat = $this->formatRegistry->getByExtension($this->extensionOf($original));
        if ($originalFormat === null) {
            $this->skip($crop, sprintf('the file "%s" has an image extension no format claims', $original));

            return null;
        }
        if ($breakpoint->getTargetWidth() < 1) {
            $this->skip($crop, 'its breakpoint has no target width');

            return null;
        }

        $spec = $breakpoint->toSpec();
        $rect = $crop->getCropRect();
        $dimensions = $rect === null
            ? $this->cropTargetSize->resolveWithoutRect($spec)
            : $this->cropTargetSize->resolve($spec, $rect);
        if ($dimensions === null) {
            $this->skip($crop, 'it has no crop rectangle and its breakpoint has no target height');

            return null;
        }

        $images = $this->variantImages($crop, $originalFormat, $variantPreference);
        $images[] = new ImageFile($original, $originalFormat);

        return new PictureSource($spec, $dimensions, $images);
    }

    /**
     * The generated variant files of a crop in preference order, leaving out any in the original's format
     *
     * @param ResponsiveCropInterface $crop
     * @param ImageFormat $originalFormat
     * @param array<string,int> $variantPreference
     * @return list<ImageFile>
     */
    private function variantImages(
        ResponsiveCropInterface $crop,
        ImageFormat $originalFormat,
        array $variantPreference
    ): array {
        $images = [];
        foreach ($crop->getVariants() as $variant) {
            $code = $variant->getFormat();
            $path = $variant->getPath();
            if ($path === null || $code === $originalFormat->getCode() || !isset($variantPreference[$code])) {
                continue;
            }
            $images[$variantPreference[$code]] = new ImageFile($path, $this->formatRegistry->get($code));
        }
        ksort($images);

        return array_values($images);
    }

    /**
     * The extension of the file name at the end of a path, without the dot; empty when it has none
     *
     * @param string $path
     * @return string
     */
    private function extensionOf(string $path): string
    {
        $slash = strrpos($path, '/');
        $name = $slash === false ? $path : substr($path, $slash + 1);
        $dot = strrpos($name, '.');

        return $dot === false ? '' : substr($name, $dot + 1);
    }

    /**
     * Log a crop left out of the picture
     *
     * @param ResponsiveCropInterface $crop
     * @param string $reason
     * @return void
     */
    private function skip(ResponsiveCropInterface $crop, string $reason): void
    {
        $this->logger->warning(
            sprintf('Banner slider: a responsive crop is not rendered because %s.', $reason),
            ['crop_id' => $crop->getCropId(), 'banner_id' => $crop->getBannerId()]
        );
    }
}
