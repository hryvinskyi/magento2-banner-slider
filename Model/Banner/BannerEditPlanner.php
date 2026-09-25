<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Banner;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeValidator;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Validation\BannerValidatorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;

/**
 * Checks a banner save completely before anything is written, and reports every error in one exception.
 *
 * - It validates the banner and reads the size of its image from the file.
 * - A new banner image, or a changed one, must be an upload of the banner slider (under the package's image folder);
 *   an unchanged image stays valid wherever it lives, so a migrated banner can still be saved. A banner pointed at any
 *   other media file (a download, a customer upload) would publish it.
 * - It checks every crop input against the banner, its slider's breakpoints and its stored crops.
 * - Stored crops that no longer apply are listed for deletion: every crop of a banner that moves to another slider
 *   (they were made for the previous slider's breakpoints), and, when the banner image changes, every crop cut from
 *   the previous image (its source is empty or that image) for which the save sends no new crop.
 */
class BannerEditPlanner
{
    private const UPLOAD_ROOT = 'image';

    /**
     * @param BannerRepositoryInterface $bannerRepository
     * @param ResponsiveCropRepositoryInterface $cropRepository
     * @param BannerValidatorInterface $bannerValidator
     * @param BannerImageSizer $imageSizer
     * @param CropChangeValidator $cropChangeValidator
     * @param MediaPaths $mediaPaths
     */
    public function __construct(
        private readonly BannerRepositoryInterface $bannerRepository,
        private readonly ResponsiveCropRepositoryInterface $cropRepository,
        private readonly BannerValidatorInterface $bannerValidator,
        private readonly BannerImageSizer $imageSizer,
        private readonly CropChangeValidator $cropChangeValidator,
        private readonly MediaPaths $mediaPaths
    ) {
    }

    /**
     * The checked plan of a banner save
     *
     * @param BannerInterface $banner
     * @param list<CropInput> $crops
     * @return BannerEditPlan
     * @throws ValidationException With every error of the banner and its crops
     */
    public function plan(BannerInterface $banner, array $crops): BannerEditPlan
    {
        $stored = $this->storedBanner($banner);
        $errors = [...$this->bannerErrors($banner), ...$this->imageLocationErrors($banner, $stored)];
        $dimensions = null;
        try {
            $dimensions = $this->imageSizer->resolve($banner, $stored);
        } catch (LocalizedException $exception) {
            $errors[] = new Phrase($exception->getMessage());
        }
        $storedCrops = $this->storedCrops($stored);
        $movesToAnotherSlider = $stored !== null && $stored->getSliderId() !== $banner->getSliderId();
        $staleCrops = $movesToAnotherSlider
            ? $storedCrops
            : $this->cropsOfReplacedImage($banner, $stored, $storedCrops, $crops);
        $changeSet = $this->cropChangeValidator->check(
            $banner,
            $crops,
            array_diff_key($storedCrops, $staleCrops),
            $stored?->getImage()
        );
        array_push($errors, ...$changeSet->getErrors());
        if ($errors !== []) {
            throw new ValidationException(
                __('The banner is not valid: %1', implode(' ', array_map(
                    static fn (Phrase $error): string => $error->render(),
                    $errors
                ))),
                null,
                0,
                new ValidationResult($errors)
            );
        }

        return new BannerEditPlan(
            $dimensions,
            $changeSet->getChanges(),
            array_values($staleCrops),
            $stored?->getSliderId()
        );
    }

    /**
     * The error of a new or changed banner image that is not an upload of the banner slider
     *
     * @param BannerInterface $banner
     * @param BannerInterface|null $stored
     * @return list<Phrase>
     */
    private function imageLocationErrors(BannerInterface $banner, ?BannerInterface $stored): array
    {
        $image = $banner->getImage();
        if ($image === null || $image === $stored?->getImage() || $this->isUpload($image)) {
            return [];
        }

        return [__(
            'The banner image "%1" is not an image uploaded for the banner slider. Upload the image instead.',
            $image
        )];
    }

    /**
     * Whether a media path lies in the package's image upload folder
     *
     * @param string $image
     * @return bool
     */
    private function isUpload(string $image): bool
    {
        try {
            return str_starts_with(
                $this->mediaPaths->assertWritable($image),
                $this->mediaPaths->getRoot(self::UPLOAD_ROOT) . '/'
            );
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Stored crops cut from the image the save replaces, for breakpoints the save sends no crop for
     *
     * @param BannerInterface $banner
     * @param BannerInterface|null $stored
     * @param array<int,ResponsiveCropInterface> $storedCrops By breakpoint id
     * @param list<CropInput> $crops
     * @return array<int,ResponsiveCropInterface> By breakpoint id
     */
    private function cropsOfReplacedImage(
        BannerInterface $banner,
        ?BannerInterface $stored,
        array $storedCrops,
        array $crops
    ): array {
        if ($stored === null || $stored->getImage() === $banner->getImage()) {
            return [];
        }
        $previousImage = $stored->getImage();
        $sent = [];
        foreach ($crops as $crop) {
            $sent[$crop->getBreakpointId()] = true;
        }

        return array_filter(
            $storedCrops,
            static fn (ResponsiveCropInterface $crop, int $breakpointId): bool => !isset($sent[$breakpointId])
                && in_array($crop->getSourceImage(), [null, $previousImage], true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * The stored version of the banner, or null for a new one
     *
     * @param BannerInterface $banner
     * @return BannerInterface|null
     * @throws ValidationException When the banner has an id no stored banner has
     */
    private function storedBanner(BannerInterface $banner): ?BannerInterface
    {
        $bannerId = $banner->getBannerId();
        if ($bannerId === null) {
            return null;
        }
        try {
            return $this->bannerRepository->getById($bannerId);
        } catch (NoSuchEntityException $exception) {
            $error = __('The banner with id "%1" does not exist.', $bannerId);
            throw new ValidationException($error, $exception, 0, new ValidationResult([$error]));
        }
    }

    /**
     * The banner's own validation errors
     *
     * @param BannerInterface $banner
     * @return list<Phrase>
     */
    private function bannerErrors(BannerInterface $banner): array
    {
        try {
            $this->bannerValidator->validate($banner);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->getErrors() as $error) {
                $errors[] = new Phrase($error->getMessage());
            }

            return $errors === [] ? [new Phrase($exception->getMessage())] : $errors;
        }

        return [];
    }

    /**
     * The stored crops of the banner by breakpoint id
     *
     * @param BannerInterface|null $stored
     * @return array<int,ResponsiveCropInterface>
     */
    private function storedCrops(?BannerInterface $stored): array
    {
        $bannerId = $stored?->getBannerId();
        if ($bannerId === null) {
            return [];
        }
        $crops = [];
        foreach ($this->cropRepository->getByBannerId($bannerId) as $crop) {
            $breakpointId = $crop->getBreakpointId();
            if ($breakpointId !== null) {
                $crops[$breakpointId] = $crop;
            }
        }

        return $crops;
    }
}
