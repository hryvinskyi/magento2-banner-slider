<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Banner;

use Hryvinskyi\BannerSlider\Model\Cache\TagCleaner;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeApplier;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileLedger;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileTransaction;
use Hryvinskyi\BannerSliderApi\Api\Banner\BannerEditorInterface;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\DataObject;

/**
 * Saves a banner and its crop changes as one unit.
 *
 * Every input is checked first, before anything is written, so a save that is not valid opens no transaction and
 * cannot roll back an outer one. Then, in one database transaction:
 * 1. the banner is saved, with its image size read from the file (a new banner gets the id its crop file names need);
 * 2. the stored crops that no longer apply are deleted: those of a banner that moved to another slider, and those
 *    cut from the image the save replaces. They go first, so a new crop for the same breakpoint never meets the old
 *    row on the unique key;
 * 3. the new crop files are written;
 * 4. the crops and their variants are saved or deleted;
 * 5. the transaction commits.
 *
 * Inside the transaction only storage can fail. Only after the commit are the replaced and deleted crop files removed,
 * and the cached pages of the banner and of its previous and current slider cleaned. On any failure the transaction
 * rolls back, only the files this save created are removed, and a new banner forgets the id the failed insert gave
 * it, so the same object can be saved again.
 *
 * Inside an outer transaction (a data patch runs in one) the commit here does not reach the database yet: files are
 * removed once the outer transaction commits, and the cache is cleaned at once.
 */
class BannerEditor implements BannerEditorInterface
{
    /**
     * @param BannerEditPlanner $planner
     * @param BannerRepositoryInterface $bannerRepository
     * @param CropChangeApplier $cropChangeApplier
     * @param CropFileTransaction $cropFileTransaction
     * @param TagCleaner $tagCleaner
     */
    public function __construct(
        private readonly BannerEditPlanner $planner,
        private readonly BannerRepositoryInterface $bannerRepository,
        private readonly CropChangeApplier $cropChangeApplier,
        private readonly CropFileTransaction $cropFileTransaction,
        private readonly TagCleaner $tagCleaner
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(BannerInterface $banner, array $crops): BannerInterface
    {
        $plan = $this->planner->plan($banner, $crops);
        $idBefore = $banner->getBannerId();

        try {
            $saved = $this->cropFileTransaction->run(
                function (CropFileLedger $ledger) use ($banner, $plan): BannerInterface {
                    $banner->setImageDimensions($plan->getImageDimensions());
                    $saved = $this->bannerRepository->save($banner);
                    $this->cropChangeApplier->release($plan->getStaleCrops());
                    $this->cropChangeApplier->apply($plan->getCropChanges(), $saved, $ledger);

                    return $saved;
                }
            );
        } catch (\Throwable $exception) {
            $this->forgetUnsavedId($banner, $idBefore);
            throw $exception;
        }

        $this->tagCleaner->clean($this->cacheTags($saved, $plan->getPreviousSliderId()));

        return $saved;
    }

    /**
     * Drop the id a rolled-back insert gave a new banner, so saving it again inserts it again
     *
     * @param BannerInterface $banner
     * @param int|null $idBefore The id the banner had before the save
     * @return void
     */
    private function forgetUnsavedId(BannerInterface $banner, ?int $idBefore): void
    {
        if ($idBefore === null && $banner instanceof DataObject) {
            $banner->unsetData(BannerInterface::BANNER_ID);
        }
    }

    /**
     * Tags of the pages showing the banner: its own and those of its current and previous slider
     *
     * @param BannerInterface $banner
     * @param int|null $previousSliderId
     * @return list<string>
     */
    private function cacheTags(BannerInterface $banner, ?int $previousSliderId): array
    {
        $tags = [BannerInterface::CACHE_TAG . '_' . (int)$banner->getBannerId()];
        foreach ([$banner->getSliderId(), $previousSliderId] as $sliderId) {
            if ($sliderId !== null) {
                $tags[] = SliderInterface::CACHE_TAG . '_' . $sliderId;
            }
        }

        return array_values(array_unique($tags));
    }
}
