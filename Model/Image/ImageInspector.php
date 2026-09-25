<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;

/**
 * Tells what a local file really is: its sniffed MIME type and its pixel size.
 *
 * The type comes from the file's bytes, never from its name or a client, and the image header must agree with it;
 * a file where the two disagree, or whose size cannot be read, is not treated as an image. Media files are inspected
 * through a local copy (LocalFileWorkspace).
 */
class ImageInspector
{
    /**
     * @param LocalFileDriver $localDriver
     */
    public function __construct(
        private readonly LocalFileDriver $localDriver
    ) {
    }

    /**
     * The MIME type and size of an image file, or null when the file is not a readable image
     *
     * @param string $localPath Absolute path on the local disk
     * @return array{mime: string, width: int, height: int}|null
     */
    public function inspect(string $localPath): ?array
    {
        try {
            $bytes = $this->localDriver->fileGetContents($localPath);
        } catch (FileSystemException) {
            return null;
        }
        if ($bytes === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $info = getimagesizefromstring($bytes);
        if (!is_string($mime) || $info === false || $info['mime'] !== $mime) {
            return null;
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1) {
            return null;
        }

        return ['mime' => $mime, 'width' => $width, 'height' => $height];
    }
}
