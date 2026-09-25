<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSlider\Model\Image\MediaImageReader;
use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Validation\ValidationException;

/**
 * Checks a banner's crop inputs before anything is written, and collects every error at once.
 *
 * For each input:
 * - its breakpoint belongs to the banner's slider, and no other input names the same breakpoint;
 * - unless the crop is removed:
 *   - the source image is one of: the crop's current source; the banner's image, but only while it is the image the
 *     stored banner already has; or a file under the package upload folders (`di.xml`). Any other media file is
 *     refused, a banner image the same save changes included, so an admin who may edit banners cannot publish a
 *     protected file (a download, a customer upload) by cropping it or by pointing the banner at it first;
 *   - the source is a readable image, and the crop area fits inside it;
 *   - its output can be produced (see CropOutputPlanner): every requested format is a variant format, browser-encoded
 *     bytes pass their checks or the server encodes the format.
 *
 * A change that passes carries its checked output plan, so applying it can fail only on storage.
 */
class CropChangeValidator
{
    /**
     * @param BreakpointRepositoryInterface $breakpointRepository
     * @param MediaImageReader $imageReader
     * @param MediaPaths $mediaPaths
     * @param CropOutputPlanner $outputPlanner
     * @param array<string> $sourceRoots Purposes of the package media roots a crop source may be taken from
     */
    public function __construct(
        private readonly BreakpointRepositoryInterface $breakpointRepository,
        private readonly MediaImageReader $imageReader,
        private readonly MediaPaths $mediaPaths,
        private readonly CropOutputPlanner $outputPlanner,
        private readonly array $sourceRoots = ['image', 'breakpoint']
    ) {
    }

    /**
     * Check the inputs against the banner and its stored crops
     *
     * @param BannerInterface $banner The banner as it will be saved
     * @param list<CropInput> $inputs
     * @param array<int,ResponsiveCropInterface> $storedCrops The banner's stored crops by breakpoint id
     * @param string|null $storedImage The image of the banner as it is stored, or null for a new banner
     * @return CropChangeSet
     */
    public function check(
        BannerInterface $banner,
        array $inputs,
        array $storedCrops,
        ?string $storedImage
    ): CropChangeSet {
        $sliderId = $banner->getSliderId();
        $breakpoints = [];
        foreach ($sliderId === null ? [] : $this->breakpointRepository->getBySliderId($sliderId) as $breakpoint) {
            $breakpoints[(int)$breakpoint->getBreakpointId()] = $breakpoint;
        }

        $changes = [];
        $errors = [];
        $seen = [];
        $images = [];
        foreach ($inputs as $input) {
            $breakpointId = $input->getBreakpointId();
            if (isset($seen[$breakpointId])) {
                $errors[] = __('More than one crop is given for breakpoint %1.', $breakpointId);
                continue;
            }
            $seen[$breakpointId] = true;
            $breakpoint = $breakpoints[$breakpointId] ?? null;
            if ($breakpoint === null) {
                $errors[] = __('Breakpoint %1 does not belong to the slider of this banner.', $breakpointId);
                continue;
            }
            $current = $storedCrops[$breakpointId] ?? null;
            if ($input->shouldRemove()) {
                $changes[] = new CropChange($input, $breakpoint, $current, null);
                continue;
            }

            $source = $this->source($banner, $storedImage, $input, $breakpoint, $current, $images);
            if ($source instanceof Phrase) {
                $errors[] = $source;
                continue;
            }
            $geometryErrors = $this->geometryErrors($input, $breakpoint, $source);
            if ($geometryErrors !== []) {
                array_push($errors, ...$geometryErrors);
                continue;
            }
            try {
                $output = $this->outputPlanner->plan($input, $breakpoint, $source);
            } catch (ValidationException $exception) {
                array_push($errors, ...$this->messagesOf($exception));
                continue;
            }
            $changes[] = new CropChange($input, $breakpoint, $current, $output);
        }

        return new CropChangeSet($changes, $errors);
    }

    /**
     * The allowed, readable source image of an input, or the reason it cannot be used
     *
     * @param BannerInterface $banner
     * @param string|null $storedImage
     * @param CropInput $input
     * @param BreakpointInterface $breakpoint
     * @param ResponsiveCropInterface|null $current
     * @param array<string,MediaImage|string> $images Sources already read in this check (or why not), by path
     * @return MediaImage|Phrase
     */
    private function source(
        BannerInterface $banner,
        ?string $storedImage,
        CropInput $input,
        BreakpointInterface $breakpoint,
        ?ResponsiveCropInterface $current,
        array &$images
    ): MediaImage|Phrase {
        $identifier = $breakpoint->getIdentifier();
        $path = $input->getSourceImage() ?? $banner->getImage();
        if ($path === null) {
            return __('The crop for breakpoint "%1" has no image to cut: the banner has no image.', $identifier);
        }
        $unchangedBannerImage = $banner->getImage() === $storedImage ? $storedImage : null;
        $safePath = $this->allowedPath($path, $unchangedBannerImage, $current);
        if ($safePath === null) {
            return __(
                'The crop for breakpoint "%1" may not use the image "%2". Use the banner image or upload an image.',
                $identifier,
                $path
            );
        }

        $image = $images[$safePath] ??= $this->read($safePath);

        return is_string($image)
            ? __('The crop for breakpoint "%1" cannot use its image: %2', $identifier, $image)
            : $image;
    }

    /**
     * The path in its media-relative form when a crop may be cut from it, otherwise null
     *
     * @param string $path
     * @param string|null $unchangedBannerImage The banner image when the save keeps the stored one, otherwise null
     * @param ResponsiveCropInterface|null $current
     * @return string|null
     */
    private function allowedPath(
        string $path,
        ?string $unchangedBannerImage,
        ?ResponsiveCropInterface $current
    ): ?string {
        try {
            $safePath = $this->mediaPaths->assertSafe($path);
        } catch (\InvalidArgumentException) {
            return null;
        }
        if ($safePath === $current?->getSourceImage() || $safePath === $unchangedBannerImage) {
            return $safePath;
        }
        foreach ($this->sourceRoots as $purpose) {
            if (str_starts_with($safePath, $this->mediaPaths->getRoot($purpose) . '/')) {
                return $safePath;
            }
        }

        return null;
    }

    /**
     * Read a source image, or the reason it cannot be used
     *
     * @param string $path
     * @return MediaImage|string
     */
    private function read(string $path): MediaImage|string
    {
        try {
            return $this->imageReader->read($path);
        } catch (LocalizedException $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * Errors of the crop area against its source
     *
     * @param CropInput $input
     * @param BreakpointInterface $breakpoint
     * @param MediaImage $source
     * @return list<Phrase>
     */
    private function geometryErrors(CropInput $input, BreakpointInterface $breakpoint, MediaImage $source): array
    {
        $rect = $input->getRect();
        if ($rect === null) {
            return [__('The crop for breakpoint "%1" needs a crop area.', $breakpoint->getIdentifier())];
        }
        $size = $source->getDimensions();
        if ($rect->fitsWithin($size)) {
            return [];
        }

        return [__(
            'The crop area %1x%2 at %3,%4 for breakpoint "%5" does not fit inside the %6x%7 source image.',
            $rect->getWidth(),
            $rect->getHeight(),
            $rect->getX(),
            $rect->getY(),
            $breakpoint->getIdentifier(),
            $size->getWidth(),
            $size->getHeight()
        )];
    }

    /**
     * The messages a validation exception carries, or its own message when it carries none
     *
     * @param ValidationException $exception
     * @return list<Phrase>
     */
    private function messagesOf(ValidationException $exception): array
    {
        $messages = [];
        foreach ($exception->getErrors() as $error) {
            $messages[] = new Phrase($error->getMessage());
        }

        return $messages === [] ? [new Phrase($exception->getMessage())] : $messages;
    }
}
