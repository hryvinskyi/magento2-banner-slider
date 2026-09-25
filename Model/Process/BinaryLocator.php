<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Process;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;

/**
 * Finds an executable by name in the folders configured in `di.xml`, searched in order.
 *
 * A relative folder is resolved against the project root. The name must be a plain file name; a path is never
 * accepted, so configuration cannot point the encoders at an arbitrary file.
 */
class BinaryLocator
{
    private const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    /**
     * @param DirectoryList $directoryList
     * @param LocalFileDriver $localDriver
     * @param array<string> $directories Folders to search in order, absolute or relative to the project root
     */
    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly LocalFileDriver $localDriver,
        private readonly array $directories = ['vendor/bin']
    ) {
    }

    /**
     * The absolute path of the first executable file with this name, or null when none is found
     *
     * @param string $binary Plain file name, such as `cwebp`
     * @return string|null
     */
    public function locate(string $binary): ?string
    {
        if (preg_match(self::NAME_PATTERN, $binary) !== 1) {
            return null;
        }

        foreach ($this->directories as $directory) {
            $candidate = $this->resolve($directory) . '/' . $binary;
            if ($this->isExecutableFile($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Whether the path is a regular file the PHP process may execute
     *
     * @param string $path
     * @return bool
     */
    private function isExecutableFile(string $path): bool
    {
        try {
            return $this->localDriver->isFile($path) && is_executable($path);
        } catch (FileSystemException) {
            return false;
        }
    }

    /**
     * The folder as an absolute path without a trailing slash
     *
     * @param string $directory
     * @return string
     */
    private function resolve(string $directory): string
    {
        $folder = rtrim($directory, '/');

        return str_starts_with($directory, '/') ? $folder : rtrim($this->directoryList->getRoot(), '/') . '/' . $folder;
    }
}
