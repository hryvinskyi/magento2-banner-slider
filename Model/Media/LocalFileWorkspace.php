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
use Magento\Framework\Filesystem\Driver\StatefulFile;

/**
 * Local files for libraries that need a path on disk (image libraries, command-line encoders).
 *
 * Temp files live in one folder under `var/`. A media file is handed out as a temp copy that is removed once the
 * callback returns or throws; on local-disk media the file's own path is handed out instead, because nothing has to
 * be copied or cleaned up there.
 */
class LocalFileWorkspace
{
    private const EXTENSION_PATTERN = '/^[a-z0-9]{1,16}$/';

    /**
     * @var WriteInterface|null
     */
    private ?WriteInterface $varDirectory = null;

    /**
     * @var WriteInterface|null
     */
    private ?WriteInterface $mediaDirectory = null;

    /**
     * @param Filesystem $filesystem
     * @param MediaPaths $mediaPaths
     * @param string $tempFolder Folder for temp files, relative to `var/`
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly MediaPaths $mediaPaths,
        private readonly string $tempFolder = 'tmp/hryvinskyi_banner_slider'
    ) {
    }

    /**
     * Run a callback with a local path holding the media file's bytes
     *
     * The callback must treat the path as read-only: on local-disk media it is the media file itself.
     *
     * @template T
     * @param string $mediaPath Safe path relative to the media directory
     * @param callable(string):T $callback Receives the absolute local path
     * @return T
     * @throws \InvalidArgumentException When the media path is not safe
     * @throws FileSystemException When the media file does not exist or cannot be copied
     * @throws ValidatorException
     */
    public function withLocalCopy(string $mediaPath, callable $callback): mixed
    {
        $safePath = $this->mediaPaths->assertSafe($mediaPath);
        $media = $this->media();
        if (!$media->isFile($safePath)) {
            throw new FileSystemException(__('The media file "%1" does not exist.', $safePath));
        }
        if ($this->isLocalDisk($media)) {
            return $callback($media->getAbsolutePath($safePath));
        }

        $localPath = $this->newTempPath($this->extensionOf($safePath));
        try {
            $this->temp()->writeFile($this->relativeTempPath($localPath), $media->readFile($safePath));

            return $callback($localPath);
        } finally {
            $this->discard($localPath);
        }
    }

    /**
     * A new, not yet existing, absolute path in the temp folder; the caller removes it with discard()
     *
     * @param string $extension Lowercase letters or digits, without the dot
     * @return string
     * @throws \InvalidArgumentException When the extension is not valid
     * @throws FileSystemException When the temp folder cannot be created
     * @throws ValidatorException
     */
    public function newTempPath(string $extension): string
    {
        if (preg_match(self::EXTENSION_PATTERN, $extension) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('A temp file extension must be 1-16 lowercase letters or digits, got "%s".', $extension)
            );
        }
        $var = $this->temp();
        $var->create($this->tempFolder);

        return $var->getAbsolutePath($this->tempFolder . '/' . bin2hex(random_bytes(16)) . '.' . $extension);
    }

    /**
     * Remove a temp file this workspace handed out; a missing file is not an error
     *
     * @param string $localPath Absolute path returned by newTempPath()
     * @return void
     * @throws \InvalidArgumentException When the path is not a file in the temp folder
     * @throws FileSystemException When the file exists but cannot be deleted
     * @throws ValidatorException
     */
    public function discard(string $localPath): void
    {
        $this->temp()->delete($this->relativeTempPath($localPath));
    }

    /**
     * The path of a temp file relative to `var/`
     *
     * @param string $localPath
     * @return string
     * @throws \InvalidArgumentException When the path is not a file in the temp folder
     * @throws FileSystemException
     */
    private function relativeTempPath(string $localPath): string
    {
        $folder = rtrim($this->temp()->getAbsolutePath($this->tempFolder), '/') . '/';
        $name = str_starts_with($localPath, $folder) ? substr($localPath, strlen($folder)) : '';
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_starts_with($name, '.')) {
            throw new \InvalidArgumentException('Only temp files of the banner slider workspace can be discarded.');
        }

        return $this->tempFolder . '/' . $name;
    }

    /**
     * The extension of a media path, lowercased, or `tmp` when it has none a temp name can carry
     *
     * @param string $path
     * @return string
     */
    private function extensionOf(string $path): string
    {
        $name = substr($path, (int)strrpos('/' . $path, '/'));
        $dot = strrpos($name, '.');
        $extension = $dot === false ? '' : strtolower(substr($name, $dot + 1));

        return preg_match(self::EXTENSION_PATTERN, $extension) === 1 ? $extension : 'tmp';
    }

    /**
     * Whether the media directory is on the local disk, so its files already have a local path
     *
     * @param WriteInterface $media
     * @return bool
     */
    private function isLocalDisk(WriteInterface $media): bool
    {
        $driver = $media->getDriver();

        return $driver instanceof LocalFileDriver || $driver instanceof StatefulFile;
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

    /**
     * The `var/` directory that holds the temp folder
     *
     * @return WriteInterface
     * @throws FileSystemException
     */
    private function temp(): WriteInterface
    {
        return $this->varDirectory ??= $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }
}
