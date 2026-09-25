<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Psr\Clock\ClockInterface;

/**
 * Copies a validated upload into media under a name derived from its content.
 *
 * The stored path is `<root>/<yyyy>/<mm>/<slug>-<hash>.<extension>`:
 * - the slug is only a readable hint made from the client file name (lowercase letters, digits and dashes, at most 64
 *   characters, `file` when nothing is left);
 * - the hash is the first 12 hex characters of the SHA-256 of the bytes;
 * - the extension is the one the caller derived from the sniffed type.
 *
 * The same bytes under the same name in the same month get the same path, and an existing file there is kept as it
 * is instead of being written again. Its modification time is set to now, so the orphan sweep's grace period starts
 * over: the new upload is as fresh as a newly written file, even when an earlier upload of the same bytes is old
 * enough to be swept.
 */
class UploadedFileStore
{
    private const HASH_LENGTH = 12;
    private const SLUG_MAX_LENGTH = 64;
    private const FALLBACK_SLUG = 'file';
    private const EXTENSION_PATTERN = '/^[a-z0-9]{1,16}$/';

    /**
     * @param MediaStorage $mediaStorage
     * @param ClockInterface $clock
     */
    public function __construct(
        private readonly MediaStorage $mediaStorage,
        private readonly ClockInterface $clock
    ) {
    }

    /**
     * Store the received file under a package root and return its media-relative path
     *
     * @param UploadedFile $file A file the upload validator accepted
     * @param string $root Package media root, relative to the media directory
     * @param string $extension Extension of the sniffed type, lowercase, without the dot
     * @return string
     * @throws \InvalidArgumentException When the extension is not lowercase letters or digits
     * @throws CouldNotSaveException When the file cannot be read or stored
     */
    public function store(UploadedFile $file, string $root, string $extension): string
    {
        if (preg_match(self::EXTENSION_PATTERN, $extension) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('A stored upload needs a lowercase extension without the dot, got "%s".', $extension)
            );
        }
        $localPath = $file->getTemporaryPath();
        $hash = $localPath === '' ? false : hash_file('sha256', $localPath);
        if ($hash === false) {
            throw new CouldNotSaveException(__('The uploaded file could not be stored.'));
        }

        $path = sprintf(
            '%s/%s/%s-%s.%s',
            trim($root, '/'),
            $this->clock->now()->format('Y/m'),
            $this->slug($file->getClientFileName()),
            substr($hash, 0, self::HASH_LENGTH),
            $extension
        );
        try {
            if ($this->mediaStorage->exists($path)) {
                $this->mediaStorage->touch($path);
            } else {
                $this->mediaStorage->copyFromLocal($localPath, $path);
            }
        } catch (FileSystemException | ValidatorException | \InvalidArgumentException $exception) {
            throw new CouldNotSaveException(__('The uploaded file could not be stored.'), $exception);
        }

        return $path;
    }

    /**
     * A readable file-name base made from the client file name, without its folder and extension
     *
     * @param string $clientFileName
     * @return string
     */
    private function slug(string $clientFileName): string
    {
        $name = (string)preg_replace('~^.*[/\\\\]~', '', $clientFileName);
        $dot = strrpos($name, '.');
        if ($dot !== false && $dot > 0) {
            $name = substr($name, 0, $dot);
        }
        $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        $slug = trim(substr($slug, 0, self::SLUG_MAX_LENGTH), '-');

        return $slug === '' ? self::FALLBACK_SLUG : $slug;
    }
}
