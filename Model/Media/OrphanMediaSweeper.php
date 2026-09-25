<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Deletes files in the package media folders that no slider data references any more.
 *
 * - Every file under every package root is a candidate (uploads, crop output, legacy folders, temp uploads).
 * - One reference snapshot is taken for the whole run: stored paths read the way the storefront reads them, plus
 *   paths quoted in banner content and slider CSS. If it cannot be taken, nothing is deleted.
 * - A file changed within the grace period is kept, so an upload not yet saved with its banner survives.
 * - A dry run only lists what would be deleted. A delete that fails is logged and the run goes on.
 */
class OrphanMediaSweeper
{
    /**
     * @param MediaStorage $mediaStorage
     * @param MediaPaths $mediaPaths
     * @param MediaReferenceIndex $referenceIndex
     * @param ClockInterface $clock
     * @param LoggerInterface $logger
     * @param int $gracePeriodSeconds Minimum age of a file before it may be deleted
     */
    public function __construct(
        private readonly MediaStorage $mediaStorage,
        private readonly MediaPaths $mediaPaths,
        private readonly MediaReferenceIndex $referenceIndex,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly int $gracePeriodSeconds = 86400
    ) {
    }

    /**
     * Delete the unreferenced files older than the grace period, or only list them
     *
     * @param bool $dryRun List only, delete nothing
     * @return list<string> Paths deleted, or that a real run would delete
     * @throws FileSystemException When a package folder cannot be listed
     * @throws ValidatorException
     */
    public function sweep(bool $dryRun = false): array
    {
        $snapshot = $this->referenceIndex->snapshot();
        $cutoff = $this->clock->now()->getTimestamp() - max(0, $this->gracePeriodSeconds);

        $orphans = [];
        foreach (array_unique($this->mediaPaths->getRoots()) as $root) {
            foreach ($this->mediaStorage->listFiles($root) as $path) {
                if (!$snapshot->isReferenced($path) && $this->isOlderThan($path, $cutoff)) {
                    $orphans[] = $path;
                }
            }
        }
        if ($dryRun) {
            return $orphans;
        }

        $deleted = [];
        foreach ($orphans as $path) {
            try {
                $this->mediaStorage->delete($path);
                $deleted[] = $path;
            } catch (\Throwable $exception) {
                $this->logger->error(
                    sprintf('Banner slider: the unreferenced media file "%s" could not be deleted.', $path),
                    ['exception' => $exception]
                );
            }
        }
        if ($deleted !== []) {
            $this->logger->info(
                sprintf('Banner slider: deleted %d unreferenced media file(s).', count($deleted)),
                ['paths' => $deleted]
            );
        }

        return $deleted;
    }

    /**
     * Whether the file was last changed before the cutoff; a file whose age is unknown is kept
     *
     * @param string $path
     * @param int $cutoff Unix timestamp
     * @return bool
     */
    private function isOlderThan(string $path, int $cutoff): bool
    {
        try {
            return $this->mediaStorage->modifiedAt($path) < $cutoff;
        } catch (\Throwable $exception) {
            $this->logger->warning(
                sprintf('Banner slider: the age of media file "%s" is unknown; it is kept.', $path),
                ['exception' => $exception]
            );

            return false;
        }
    }
}
