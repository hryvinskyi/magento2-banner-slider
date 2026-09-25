<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Media\MediaStorage;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\CouldNotSaveException;
use Psr\Log\LoggerInterface;

/**
 * Stores crop output files under their content-hash names, and discards the ones a failed save created.
 *
 * A file whose name already exists with the same bytes is reused and reported as not created, so undoing a failed
 * save never deletes a file that stored rows may still use. A name that exists with other bytes (a shortened-hash
 * clash) is never overwritten: the store fails instead.
 */
class CropFileStore
{
    /**
     * @param CropFileNamer $fileNamer
     * @param MediaStorage $mediaStorage
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CropFileNamer $fileNamer,
        private readonly MediaStorage $mediaStorage,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Store encoded crop bytes under their content-hash name
     *
     * @param int $bannerId
     * @param string $breakpointIdentifier
     * @param ImageFormat $format
     * @param string $bytes
     * @return StoredCropFile
     * @throws CouldNotSaveException When the file cannot be written, or its name is taken by other bytes
     */
    public function store(
        int $bannerId,
        string $breakpointIdentifier,
        ImageFormat $format,
        string $bytes
    ): StoredCropFile {
        $hash = $this->fileNamer->contentHash($bytes);
        try {
            $path = $this->fileNamer->name($bannerId, $breakpointIdentifier, $hash, $format);
            if ($this->mediaStorage->exists($path)) {
                if (!hash_equals($hash, $this->fileNamer->contentHash($this->mediaStorage->read($path)))) {
                    throw new CouldNotSaveException(__(
                        'The crop file for breakpoint "%1" cannot be stored: its name is taken by another file.',
                        $breakpointIdentifier
                    ));
                }

                return new StoredCropFile($path, false);
            }
            $this->mediaStorage->write($path, $bytes);
        } catch (CouldNotSaveException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf('Banner slider: a crop file for breakpoint "%s" could not be stored.', $breakpointIdentifier),
                ['exception' => $exception]
            );
            throw new CouldNotSaveException(
                __(
                    'The %1 crop file for breakpoint "%2" could not be stored.',
                    $format->getCode(),
                    $breakpointIdentifier
                ),
                $exception
            );
        }

        return new StoredCropFile($path, true);
    }

    /**
     * Delete files a failed save created; a failure is logged, never thrown, so the original error stays visible
     *
     * @param list<string> $paths
     * @return void
     */
    public function discard(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->mediaStorage->delete($path);
            } catch (\Throwable $exception) {
                $this->logger->error(
                    sprintf('Banner slider: the crop file "%s" of a failed save could not be deleted.', $path),
                    ['exception' => $exception]
                );
            }
        }
    }
}
