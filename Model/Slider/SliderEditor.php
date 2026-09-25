<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider;

use Hryvinskyi\BannerSlider\Model\Cache\TagCleaner;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Slider\SliderEditorInterface;
use Hryvinskyi\BannerSliderApi\Api\SliderRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\LocationCode;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;

/**
 * Saves a slider and its breakpoints in one database transaction.
 *
 * Every input is checked before the first write, and so are the crop areas a target change moves. Then, inside the
 * transaction: the slider is saved (a new one gets its id), breakpoints missing from the desired set are deleted
 * (their crops go with them), breakpoints whose identifier changes are first moved to a temporary identifier so two
 * of them can swap, the desired set is saved, and the crops of a breakpoint whose target size changes get the part of
 * their area that fits the new aspect ratio (see CropAreaRetargeter). Any failure rolls everything back, and a new
 * slider forgets the id the failed insert gave it, so the same object can be saved again.
 *
 * Only after the commit are the output files of the deleted crops removed (by the breakpoint repository; those still
 * referenced, or outside the crop output folder, stay), the crops of retargeted breakpoints rendered again at their
 * new size (a failure is logged, never thrown), and the cache tags of the slider, of its previous and current location
 * and of the re-rendered banners cleaned.
 *
 * An empty desired set for a stored slider deletes every breakpoint and its crops.
 */
class SliderEditor implements SliderEditorInterface
{
    private const RENAMING_PREFIX = 'renaming-';

    /**
     * @param ResourceConnection $resourceConnection
     * @param SliderRepositoryInterface $sliderRepository
     * @param BreakpointRepositoryInterface $breakpointRepository
     * @param BreakpointSetPlanner $planner
     * @param CropAreaRetargeter $cropAreaRetargeter
     * @param TagCleaner $tagCleaner
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly SliderRepositoryInterface $sliderRepository,
        private readonly BreakpointRepositoryInterface $breakpointRepository,
        private readonly BreakpointSetPlanner $planner,
        private readonly CropAreaRetargeter $cropAreaRetargeter,
        private readonly TagCleaner $tagCleaner
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(SliderInterface $slider, array $breakpoints): SliderInterface
    {
        $sliderId = $slider->getSliderId();
        $previousLocation = $sliderId === null ? null : $this->storedLocation($sliderId);
        $plan = $this->planner->plan($sliderId, $breakpoints);
        $retargetPlan = $this->cropAreaRetargeter->plan($plan->getRetargeted());

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $saved = $this->sliderRepository->save($slider);
            $this->applyPlan($plan, $this->savedId($saved));
            $this->cropAreaRetargeter->apply($retargetPlan);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->forgetUnsavedId($slider, $sliderId);
            throw $exception;
        }

        $this->tagCleaner->clean([
            ...$this->cacheTags($saved, $previousLocation),
            ...$this->cropAreaRetargeter->regenerate($retargetPlan),
        ]);

        return $saved;
    }

    /**
     * Drop the id a rolled-back insert gave a new slider, so saving it again inserts it again
     *
     * @param SliderInterface $slider
     * @param int|null $idBefore The id the slider had before the save
     * @return void
     */
    private function forgetUnsavedId(SliderInterface $slider, ?int $idBefore): void
    {
        if ($idBefore === null && $slider instanceof DataObject) {
            $slider->unsetData(SliderInterface::SLIDER_ID);
        }
    }

    /**
     * Delete, rename and save the planned breakpoints of the saved slider
     *
     * @param BreakpointSetPlan $plan
     * @param int $sliderId
     * @return void
     * @throws CouldNotSaveException When a breakpoint cannot be stored or deleted
     * @throws ValidationException When a breakpoint is not valid
     */
    private function applyPlan(BreakpointSetPlan $plan, int $sliderId): void
    {
        foreach ($plan->getToDelete() as $breakpoint) {
            $this->delete($breakpoint);
        }
        foreach ($plan->getRenamed() as $breakpoint) {
            $identifier = $breakpoint->getIdentifier();
            $breakpoint->setIdentifier($this->temporaryIdentifier($breakpoint, $identifier));
            $this->breakpointRepository->save($breakpoint);
            $breakpoint->setIdentifier($identifier);
        }
        foreach ($plan->getToSave() as $breakpoint) {
            $breakpoint->setSliderId($sliderId);
            $this->breakpointRepository->save($breakpoint);
        }
    }

    /**
     * Delete a breakpoint, reporting a failure as a failed save of the slider
     *
     * @param BreakpointInterface $breakpoint
     * @return void
     * @throws CouldNotSaveException
     */
    private function delete(BreakpointInterface $breakpoint): void
    {
        try {
            $this->breakpointRepository->delete($breakpoint);
        } catch (CouldNotDeleteException $exception) {
            throw new CouldNotSaveException(
                __('The breakpoint "%1" could not be deleted.', $breakpoint->getIdentifier()),
                $exception
            );
        }
    }

    /**
     * An identifier no other breakpoint of the slider uses, held while identifiers are exchanged
     *
     * @param BreakpointInterface $breakpoint
     * @param string $identifier The identifier the breakpoint ends up with
     * @return string
     */
    private function temporaryIdentifier(BreakpointInterface $breakpoint, string $identifier): string
    {
        return self::RENAMING_PREFIX . (int)$breakpoint->getBreakpointId() . '-'
            . substr(hash('sha256', $identifier), 0, 8);
    }

    /**
     * The location the stored slider is placed at
     *
     * @param int $sliderId
     * @return string|null
     * @throws ValidationException When no slider has the id
     */
    private function storedLocation(int $sliderId): ?string
    {
        try {
            return $this->sliderRepository->getById($sliderId)->getLocation();
        } catch (NoSuchEntityException $exception) {
            $error = __('The slider with id "%1" does not exist.', $sliderId);
            throw new ValidationException($error, $exception, 0, new ValidationResult([$error]));
        }
    }

    /**
     * The id of a slider the repository has just saved
     *
     * @param SliderInterface $slider
     * @return int
     * @throws CouldNotSaveException When the saved slider has no id
     */
    private function savedId(SliderInterface $slider): int
    {
        $sliderId = $slider->getSliderId();
        if ($sliderId === null) {
            throw new CouldNotSaveException(__('The slider could not be saved.'));
        }

        return $sliderId;
    }

    /**
     * Tags of the pages showing the slider: its own and those of its previous and current location
     *
     * @param SliderInterface $slider
     * @param string|null $previousLocation
     * @return list<string>
     */
    private function cacheTags(SliderInterface $slider, ?string $previousLocation): array
    {
        $tags = [SliderInterface::CACHE_TAG . '_' . (int)$slider->getSliderId()];
        foreach ([$previousLocation, $slider->getLocation()] as $location) {
            if ($location === null || $location === '') {
                continue;
            }
            try {
                $tags[] = (new LocationCode($location))->toCacheTag();
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return array_values(array_unique($tags));
    }
}
