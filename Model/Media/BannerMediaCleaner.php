<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSlider\Model\ResourceModel\AfterCommitScheduler;
use Psr\Log\LoggerInterface;

/**
 * Removes the crop output folder of a banner that has been deleted, once that delete is committed.
 *
 * Only the banner's own folder under the crop output root goes: every file in it was written for this banner alone.
 * The banner image and a local video stay, because a duplicated banner may use the same file and a path outside the
 * package folders is never deleted; the orphan sweep removes them once nothing references them.
 *
 * Call it after the delete succeeded. Inside a transaction the folder is removed only after the outermost commit and
 * kept when the transaction rolls back (see AfterCommitScheduler), so a rolled-back delete keeps its files. A failure
 * is logged, never thrown, because the row is already gone.
 */
class BannerMediaCleaner
{
    private const CROP_OUTPUT_ROOT = 'responsive';

    /**
     * @param MediaStorage $mediaStorage
     * @param MediaPaths $mediaPaths
     * @param AfterCommitScheduler $afterCommitScheduler
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MediaStorage $mediaStorage,
        private readonly MediaPaths $mediaPaths,
        private readonly AfterCommitScheduler $afterCommitScheduler,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Remove the crop files of a deleted banner now, or after the outermost commit when a transaction is open
     *
     * @param int $bannerId
     * @return void
     */
    public function removeAfterCommit(int $bannerId): void
    {
        if ($bannerId < 1) {
            return;
        }
        $folder = $this->mediaPaths->getRoot(self::CROP_OUTPUT_ROOT) . '/' . $bannerId;
        $this->afterCommitScheduler->schedule(fn () => $this->removeFolder($folder, $bannerId));
    }

    /**
     * Delete the crop folder, logging a failure
     *
     * @param string $folder
     * @param int $bannerId
     * @return void
     */
    private function removeFolder(string $folder, int $bannerId): void
    {
        try {
            $this->mediaStorage->deleteDirectory($folder);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf(
                    'Banner slider: the crop folder "%s" of deleted banner %d could not be removed.',
                    $folder,
                    $bannerId
                ),
                ['exception' => $exception]
            );
        }
    }
}
