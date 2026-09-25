<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;

/**
 * The only way core touches files in the media directory.
 *
 * Every path is relative to media and goes through the Filesystem API, so remote media storage works. Reads accept any
 * safe path; writes, moves and deletes accept only paths inside the package roots (see MediaPaths).
 */
class MediaStorage
{
    /**
     * @var WriteInterface|null
     */
    private ?WriteInterface $mediaDirectory = null;

    /**
     * @param Filesystem $filesystem
     * @param MediaPaths $mediaPaths
     * @param LocalFileDriver $localDriver Reads the local files copied into media
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly MediaPaths $mediaPaths,
        private readonly LocalFileDriver $localDriver
    ) {
    }

    /**
     * Whether a file or folder exists at the path
     *
     * @param string $path
     * @return bool
     * @throws \InvalidArgumentException When the path is not safe
     * @throws FileSystemException
     * @throws ValidatorException
     */
    public function exists(string $path): bool
    {
        return $this->media()->isExist($this->mediaPaths->assertSafe($path));
    }

    /**
     * The contents of a file
     *
     * @param string $path
     * @return string
     * @throws \InvalidArgumentException When the path is not safe
     * @throws FileSystemException When the file cannot be read
     * @throws ValidatorException
     */
    public function read(string $path): string
    {
        return $this->media()->readFile($this->mediaPaths->assertSafe($path));
    }

    /**
     * The size of a file in bytes
     *
     * @param string $path
     * @return int
     * @throws \InvalidArgumentException When the path is not safe
     * @throws FileSystemException When the file cannot be read or its size is unknown
     * @throws ValidatorException
     */
    public function size(string $path): int
    {
        $safePath = $this->mediaPaths->assertSafe($path);
        $size = $this->media()->stat($safePath)['size'] ?? null;
        if (!is_int($size)) {
            throw new FileSystemException(__('The size of the media file "%1" cannot be read.', $safePath));
        }

        return $size;
    }

    /**
     * The last modification time of a file, as a Unix timestamp
     *
     * @param string $path
     * @return int
     * @throws \InvalidArgumentException When the path is not safe
     * @throws FileSystemException When the file cannot be read or its modification time is unknown
     * @throws ValidatorException
     */
    public function modifiedAt(string $path): int
    {
        $safePath = $this->mediaPaths->assertSafe($path);
        $modifiedAt = $this->media()->stat($safePath)['mtime'] ?? null;
        if (!is_int($modifiedAt)) {
            throw new FileSystemException(
                __('The modification time of the media file "%1" cannot be read.', $safePath)
            );
        }

        return $modifiedAt;
    }

    /**
     * Write a file, creating its parent folders
     *
     * @param string $path
     * @param string $bytes
     * @return void
     * @throws \InvalidArgumentException When the path is not writable
     * @throws FileSystemException When the file cannot be written
     * @throws ValidatorException
     */
    public function write(string $path, string $bytes): void
    {
        $this->media()->writeFile($this->mediaPaths->assertWritable($path), $bytes);
    }

    /**
     * Copy a local file (a temp file or an upload) into media, creating the parent folders
     *
     * @param string $localPath Absolute path on the local disk
     * @param string $path Destination in media
     * @return void
     * @throws \InvalidArgumentException When the destination is not writable
     * @throws FileSystemException When the file cannot be copied
     * @throws ValidatorException
     */
    public function copyFromLocal(string $localPath, string $path): void
    {
        $destination = $this->mediaPaths->assertWritable($path);
        $media = $this->media();
        $media->create($this->parentOf($destination));
        $this->localDriver->copy($localPath, $media->getAbsolutePath($destination), $media->getDriver());
    }

    /**
     * Set a file's modification time to now, creating an empty file when there is none
     *
     * @param string $path
     * @return void
     * @throws \InvalidArgumentException When the path is not writable
     * @throws FileSystemException When the file cannot be touched
     * @throws ValidatorException
     */
    public function touch(string $path): void
    {
        $this->media()->touch($this->mediaPaths->assertWritable($path));
    }

    /**
     * Move a file inside the package roots, creating the parent folders of the destination
     *
     * @param string $from
     * @param string $to
     * @return void
     * @throws \InvalidArgumentException When either path is not writable
     * @throws FileSystemException When the file cannot be moved
     * @throws ValidatorException
     */
    public function move(string $from, string $to): void
    {
        $this->media()->renameFile($this->mediaPaths->assertWritable($from), $this->mediaPaths->assertWritable($to));
    }

    /**
     * Delete a file; a missing file is not an error
     *
     * @param string $path
     * @return void
     * @throws \InvalidArgumentException When the path is not writable
     * @throws FileSystemException When the file exists but cannot be deleted
     * @throws ValidatorException
     */
    public function delete(string $path): void
    {
        $this->media()->delete($this->mediaPaths->assertWritable($path));
    }

    /**
     * Delete a folder with everything in it; a missing folder is not an error
     *
     * A package root itself is never removed, only folders inside it.
     *
     * @param string $path
     * @return void
     * @throws \InvalidArgumentException When the path is not writable or is a package root
     * @throws FileSystemException When the folder exists but cannot be deleted
     * @throws ValidatorException
     */
    public function deleteDirectory(string $path): void
    {
        $writable = $this->mediaPaths->assertWritable($path);
        if ($this->mediaPaths->isRoot($writable)) {
            throw new \InvalidArgumentException(
                sprintf('The banner slider media folder "%s" itself cannot be deleted.', $writable)
            );
        }
        $this->media()->delete($writable);
    }

    /**
     * Every file below a folder inside the package roots, recursively, sorted
     *
     * @param string $directory
     * @return list<string> Paths relative to the media directory
     * @throws \InvalidArgumentException When the folder is not writable
     * @throws FileSystemException
     * @throws ValidatorException
     */
    public function listFiles(string $directory): array
    {
        $folder = $this->mediaPaths->assertWritable($directory);
        $media = $this->media();
        if (!$media->isDirectory($folder)) {
            return [];
        }

        $files = [];
        foreach ($media->getDriver()->readDirectoryRecursively($media->getAbsolutePath($folder)) as $absolutePath) {
            if (!is_string($absolutePath) || !$media->getDriver()->isFile($absolutePath)) {
                continue;
            }
            $files[] = $media->getRelativePath($absolutePath);
        }
        sort($files);

        return $files;
    }

    /**
     * The folder part of a media-relative path, or an empty string for a path in the media root
     *
     * @param string $path
     * @return string
     */
    private function parentOf(string $path): string
    {
        $slash = strrpos(rtrim($path, '/'), '/');

        return $slash === false ? '' : substr($path, 0, $slash);
    }

    /**
     * The media directory
     *
     * @return WriteInterface
     * @throws FileSystemException
     */
    private function media(): WriteInterface
    {
        return $this->mediaDirectory ??= $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }
}
