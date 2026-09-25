<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

/**
 * Asks PHP whether a local file arrived through an HTTP upload of the current request.
 *
 * The one place the package calls `is_uploaded_file()`; the upload validator depends on it, so tests can fake it.
 */
class UploadedFileChecker
{
    /**
     * Whether the file was received through an HTTP POST upload of this request
     *
     * @param string $localPath Absolute path of the received temp file
     * @return bool
     */
    public function isUploadedFile(string $localPath): bool
    {
        return $localPath !== '' && is_uploaded_file($localPath);
    }
}
