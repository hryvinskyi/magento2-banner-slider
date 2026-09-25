<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider;

use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCrop\CropRegeneratorInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Validation\ResponsiveCropValidatorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Validation\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * Keeps the crops of a breakpoint right when its target size changes, in three steps around the slider save.
 *
 * 1. Plan, before the transaction: every crop cut for a retargeted breakpoint is listed for rendering again. When the
 *    new target fixes both sides, a crop's area becomes the largest centred part of its current area with the new
 *    target's aspect ratio (the cover rule), so the crop keeps showing what the admin chose, only trimmed to the new
 *    shape; with an open height any area fits and stays. A crop whose new area would not pass the crop rules is left
 *    as it is, and that is logged.
 * 2. Apply, inside the slider save's transaction: the changed areas are saved. Only storage can fail here.
 * 3. Regenerate, after the commit: each listed crop is rendered again at its breakpoint's new target. A failure is
 *    logged, never thrown, because the slider is already saved; the crop keeps its previous files until an admin
 *    saves it again or crops are regenerated from the command line.
 */
class CropAreaRetargeter
{
    /**
     * @param CollectionFactory $cropCollectionFactory
     * @param ResponsiveCropRepositoryInterface $cropRepository
     * @param ResponsiveCropValidatorInterface $cropValidator
     * @param CropRegeneratorInterface $cropRegenerator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CollectionFactory $cropCollectionFactory,
        private readonly ResponsiveCropRepositoryInterface $cropRepository,
        private readonly ResponsiveCropValidatorInterface $cropValidator,
        private readonly CropRegeneratorInterface $cropRegenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Work out the new crop areas and the crops to render again, without writing anything
     *
     * @param list<BreakpointInterface> $retargeted Stored breakpoints already carrying their new target
     * @return CropRetargetPlan
     */
    public function plan(array $retargeted): CropRetargetPlan
    {
        $targets = [];
        foreach ($retargeted as $breakpoint) {
            $breakpointId = $breakpoint->getBreakpointId();
            if ($breakpointId !== null) {
                $targets[$breakpointId] = $breakpoint->toSpec()->getFixedTarget();
            }
        }
        if ($targets === []) {
            return new CropRetargetPlan();
        }

        $toSave = [];
        $regenerations = [];
        $crops = $this->cropCollectionFactory->create()->addBreakpointIdsFilter(array_keys($targets))->getItems();
        foreach ($crops as $crop) {
            $bannerId = $crop instanceof ResponsiveCropInterface ? $crop->getBannerId() : null;
            $breakpointId = $crop instanceof ResponsiveCropInterface ? $crop->getBreakpointId() : null;
            $area = $crop instanceof ResponsiveCropInterface ? $crop->getCropRect() : null;
            if ($bannerId === null || $breakpointId === null || $area === null) {
                continue;
            }
            $regenerations[] = ['bannerId' => $bannerId, 'breakpointId' => $breakpointId];
            $newArea = $this->coverArea($area, $targets[$breakpointId] ?? null);
            if ($this->sameArea($area, $newArea) || !$this->accepts($crop, $newArea)) {
                continue;
            }
            $toSave[] = $crop;
        }

        return new CropRetargetPlan($toSave, $regenerations);
    }

    /**
     * Save the changed crop areas; call it inside the slider save's transaction
     *
     * @param CropRetargetPlan $plan
     * @return void
     * @throws CouldNotSaveException When a crop cannot be stored
     */
    public function apply(CropRetargetPlan $plan): void
    {
        foreach ($plan->getCropsToSave() as $crop) {
            $this->cropRepository->save($crop);
        }
    }

    /**
     * Render the planned crops again at their new target, logging every failure; call it after the commit
     *
     * @param CropRetargetPlan $plan
     * @return list<string> Cache tags of the banners whose crops were rendered again
     */
    public function regenerate(CropRetargetPlan $plan): array
    {
        $tags = [];
        foreach ($plan->getRegenerations() as $regeneration) {
            try {
                $this->cropRegenerator->regenerate($regeneration['bannerId'], $regeneration['breakpointId']);
            } catch (\Throwable $exception) {
                $this->logger->error(
                    sprintf(
                        'Banner slider: the crop of banner %d for breakpoint %d could not be rendered at the '
                        . 'breakpoint\'s new target size; it keeps its previous files.',
                        $regeneration['bannerId'],
                        $regeneration['breakpointId']
                    ),
                    ['exception' => $exception]
                );
            }
            $tags[] = BannerInterface::CACHE_TAG . '_' . $regeneration['bannerId'];
        }

        return array_values(array_unique($tags));
    }

    /**
     * The largest centred part of an area with the target's aspect ratio, in source pixels
     *
     * With a target that leaves the height open, that is the area itself.
     *
     * @param CropRect $area
     * @param Dimensions|null $target
     * @return CropRect
     */
    private function coverArea(CropRect $area, ?Dimensions $target): CropRect
    {
        $inArea = (new Dimensions($area->getWidth(), $area->getHeight()))->coverRect($target);

        return new CropRect(
            $area->getX() + $inArea->getX(),
            $area->getY() + $inArea->getY(),
            $inArea->getWidth(),
            $inArea->getHeight()
        );
    }

    /**
     * Whether two areas are the same rectangle
     *
     * @param CropRect $first
     * @param CropRect $second
     * @return bool
     */
    private function sameArea(CropRect $first, CropRect $second): bool
    {
        return [$first->getX(), $first->getY(), $first->getWidth(), $first->getHeight()]
            === [$second->getX(), $second->getY(), $second->getWidth(), $second->getHeight()];
    }

    /**
     * Give the crop its new area when the crop rules accept it; otherwise keep its area and log why
     *
     * @param ResponsiveCropInterface $crop
     * @param CropRect $newArea
     * @return bool Whether the crop now carries the new area
     */
    private function accepts(ResponsiveCropInterface $crop, CropRect $newArea): bool
    {
        $previous = $crop->getCropRect();
        $crop->setCropRect($newArea);
        try {
            $this->cropValidator->validate($crop);
        } catch (ValidationException $exception) {
            $crop->setCropRect($previous);
            $this->logger->warning(
                sprintf(
                    'Banner slider: the area of crop %d was not fitted to its breakpoint\'s new target: %s',
                    (int)$crop->getCropId(),
                    $exception->getMessage()
                ),
                ['exception' => $exception]
            );

            return false;
        }

        return true;
    }
}
