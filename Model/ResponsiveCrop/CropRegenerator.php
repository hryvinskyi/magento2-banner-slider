<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\CropVariantInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCrop\CropRegeneratorInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\FormatRequest;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Re-encodes stored crops on the server from their source image, rectangle and variant qualities.
 *
 * All crops of the banner are checked in one pass (the slider's breakpoints are read once). Each crop is then one
 * unit (see CropFileTransaction): new files, the crop row in a transaction, then removal of the replaced files; a
 * failure rolls that crop back and removes only its new files. A crop is cut from its stored area, or, when an
 * earlier version stored it as "show the source as it is", from the whole-image area of its source (see
 * CropRegenerationArea). A crop without an area has nothing to cut and is skipped. A crop that fails, including one
 * left behind for a breakpoint of another slider, does not stop the others; every failure is logged and named in the
 * exception thrown at the end.
 */
class CropRegenerator implements CropRegeneratorInterface
{
    /**
     * @param BannerRepositoryInterface $bannerRepository
     * @param ResponsiveCropRepositoryInterface $cropRepository
     * @param CropRegenerationArea $regenerationArea
     * @param CropChangeValidator $cropChangeValidator
     * @param CropChangeApplier $cropChangeApplier
     * @param CropFileTransaction $cropFileTransaction
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly BannerRepositoryInterface $bannerRepository,
        private readonly ResponsiveCropRepositoryInterface $cropRepository,
        private readonly CropRegenerationArea $regenerationArea,
        private readonly CropChangeValidator $cropChangeValidator,
        private readonly CropChangeApplier $cropChangeApplier,
        private readonly CropFileTransaction $cropFileTransaction,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function regenerate(int $bannerId, ?int $breakpointId = null): int
    {
        $banner = $this->bannerRepository->getById($bannerId);
        $inputs = [];
        $storedCrops = [];
        $failures = [];
        foreach ($this->cropRepository->getByBannerId($bannerId) as $crop) {
            $cropBreakpointId = $crop->getBreakpointId();
            if ($cropBreakpointId === null || ($breakpointId !== null && $cropBreakpointId !== $breakpointId)) {
                continue;
            }
            try {
                $rect = $this->regenerationArea->resolve($crop, $banner->getImage());
                if ($rect === null) {
                    continue;
                }
                $inputs[] = $this->inputOf($crop, $cropBreakpointId, $rect);
                $storedCrops[$cropBreakpointId] = $crop;
            } catch (LocalizedException | \InvalidArgumentException $exception) {
                $failures[] = $this->failure($bannerId, $cropBreakpointId, $exception);
            }
        }

        $changeSet = $this->cropChangeValidator->check($banner, $inputs, $storedCrops, $banner->getImage());
        foreach ($changeSet->getErrors() as $error) {
            $failures[] = $error->render();
            $this->logger->error(sprintf(
                'Banner slider: a crop of banner %d could not be regenerated. %s',
                $bannerId,
                $error->render()
            ));
        }
        $regenerated = 0;
        foreach ($changeSet->getChanges() as $change) {
            try {
                $this->cropFileTransaction->run(
                    fn (CropFileLedger $ledger): int => $this->cropChangeApplier->apply([$change], $banner, $ledger)
                );
                $regenerated++;
            } catch (LocalizedException $exception) {
                $failures[] = $this->failure($bannerId, $change->getInput()->getBreakpointId(), $exception);
            }
        }

        if ($failures !== []) {
            throw new CouldNotSaveException(__(
                'Some crops of banner %1 could not be regenerated: %2',
                $bannerId,
                implode('; ', $failures)
            ));
        }

        return $regenerated;
    }

    /**
     * The desired state of a stored crop: the same source, variant qualities and status, cut from the given area
     *
     * @param ResponsiveCropInterface $crop
     * @param int $breakpointId
     * @param CropRect $rect
     * @return CropInput
     * @throws \InvalidArgumentException When a stored value cannot form a crop input
     */
    private function inputOf(ResponsiveCropInterface $crop, int $breakpointId, CropRect $rect): CropInput
    {
        return new CropInput(
            $breakpointId,
            $crop->getSourceImage(),
            $rect,
            array_map(
                static fn (CropVariantInterface $variant): FormatRequest => new FormatRequest(
                    $variant->getFormat(),
                    $variant->getQuality()
                ),
                $crop->getVariants()
            ),
            [],
            $crop->isEnabled(),
            false
        );
    }

    /**
     * Log a failed crop and describe it for the final exception
     *
     * @param int $bannerId
     * @param int $breakpointId
     * @param \Exception $exception
     * @return string
     */
    private function failure(int $bannerId, int $breakpointId, \Exception $exception): string
    {
        $this->logger->error(
            sprintf(
                'Banner slider: the crop of banner %d for breakpoint %d could not be regenerated.',
                $bannerId,
                $breakpointId
            ),
            ['exception' => $exception]
        );

        return __('breakpoint %1: %2', $breakpointId, $exception->getMessage())->render();
    }
}
