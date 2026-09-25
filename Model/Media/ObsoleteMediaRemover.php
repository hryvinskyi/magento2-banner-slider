<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSlider\Model\Data\MediaRelativePath;
use Psr\Log\LoggerInterface;

/**
 * Deletes crop output files a committed save replaced.
 *
 * Call it only after the database commit. A path is deleted only when all of these hold:
 * - it is not among `$keep`, the paths the same save wrote (a content-hash name often stays the same, so a new path
 *   can equal an old one). Paths are compared in their canonical form (see MediaRelativePath);
 * - it lies under the crop output folder. Anything else, a source image or a crop row imported from elsewhere that
 *   points at its source image, is never deleted here;
 * - no row references it any more, checked against one reference snapshot taken for the whole call.
 *
 * The database is already committed, so a failure (reading the references, deleting a file) is logged with its
 * exception and never thrown; a file left behind only takes space.
 */
class ObsoleteMediaRemover
{
    private const CROP_OUTPUT_ROOT = 'responsive';

    /**
     * @param MediaStorage $mediaStorage
     * @param MediaPaths $mediaPaths
     * @param MediaReferenceIndex $referenceIndex
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MediaStorage $mediaStorage,
        private readonly MediaPaths $mediaPaths,
        private readonly MediaReferenceIndex $referenceIndex,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Delete the replaced files that nothing uses any more
     *
     * @param list<string> $paths Media-relative paths the save replaced
     * @param list<string> $keep Media-relative paths the save wrote
     * @return void
     */
    public function remove(array $paths, array $keep = []): void
    {
        $candidates = $this->candidates($paths, $keep);
        if ($candidates === []) {
            return;
        }

        try {
            $snapshot = $this->referenceIndex->snapshot();
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Banner slider: replaced crop files were kept because the media references could not be read.',
                ['exception' => $exception, 'paths' => $candidates]
            );

            return;
        }

        foreach ($candidates as $path) {
            if ($snapshot->isReferenced($path)) {
                continue;
            }
            try {
                $this->mediaStorage->delete($path);
            } catch (\Throwable $exception) {
                $this->logger->error(
                    sprintf('Banner slider: the replaced crop file "%s" could not be deleted.', $path),
                    ['exception' => $exception]
                );
            }
        }
    }

    /**
     * The paths that may be deleted if nothing references them: safe, under the crop output folder, not kept
     *
     * @param list<string> $paths
     * @param list<string> $keep
     * @return list<string>
     */
    private function candidates(array $paths, array $keep): array
    {
        $kept = [];
        foreach ($keep as $path) {
            $normalised = $this->normalise($path);
            if ($normalised !== null) {
                $kept[$normalised] = true;
            }
        }

        $prefix = $this->mediaPaths->getRoot(self::CROP_OUTPUT_ROOT) . '/';
        $candidates = [];
        foreach ($paths as $path) {
            $normalised = $this->normalise($path);
            if ($normalised === null || isset($kept[$normalised]) || !str_starts_with($normalised, $prefix)) {
                continue;
            }
            $candidates[$normalised] = true;
        }

        return array_map('strval', array_keys($candidates));
    }

    /**
     * The path in its canonical media-relative form, or null when it is not a safe media path
     *
     * @param string $path
     * @return string|null
     */
    private function normalise(string $path): ?string
    {
        try {
            $canonical = (new MediaRelativePath(ltrim(trim($path), '/')))->toCanonicalString();
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $canonical === '' ? null : $canonical;
    }
}
