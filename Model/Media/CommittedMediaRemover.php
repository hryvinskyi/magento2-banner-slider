<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSlider\Model\ResourceModel\AfterCommitScheduler;

/**
 * Removes replaced crop output files only once the rows that stopped using them are committed.
 *
 * Outside a transaction the files go at once. Inside one the removal waits for the outermost commit and is dropped
 * when the transaction rolls back (see AfterCommitScheduler), so a rollback never leaves a restored row pointing at
 * a deleted file. Which files may really be deleted, and the logging of failures, is ObsoleteMediaRemover's.
 */
class CommittedMediaRemover
{
    /**
     * @param AfterCommitScheduler $afterCommitScheduler
     * @param ObsoleteMediaRemover $obsoleteMediaRemover
     */
    public function __construct(
        private readonly AfterCommitScheduler $afterCommitScheduler,
        private readonly ObsoleteMediaRemover $obsoleteMediaRemover
    ) {
    }

    /**
     * Remove the files now, or after the outermost commit when a transaction is open
     *
     * @param list<string> $paths Media-relative paths the committed rows no longer use
     * @param list<string> $keep Media-relative paths the same change wrote
     * @return void
     */
    public function removeAfterCommit(array $paths, array $keep = []): void
    {
        if ($paths === []) {
            return;
        }

        $this->afterCommitScheduler->schedule(fn () => $this->obsoleteMediaRemover->remove($paths, $keep));
    }
}
