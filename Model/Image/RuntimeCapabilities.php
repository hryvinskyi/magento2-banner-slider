<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

/**
 * What the PHP runtime can do with images: the probes the encoders ask before they claim a format.
 *
 * Kept to the probes themselves so encoders can be tested against a fake runtime.
 */
class RuntimeCapabilities
{
    /**
     * Whether the GD extension is loaded and reports the feature (a `gd_info()` key such as `WebP Support`)
     *
     * @param string $feature
     * @return bool
     */
    public function gdSupports(string $feature): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        return (gd_info()[$feature] ?? false) === true;
    }

    /**
     * Whether a function exists in this runtime
     *
     * @param string $function
     * @return bool
     */
    public function hasFunction(string $function): bool
    {
        return function_exists($function);
    }

    /**
     * Whether the Imagick extension is loaded and its ImageMagick build can handle the format (such as `AVIF`)
     *
     * @param string $format
     * @return bool
     */
    public function imagickSupports(string $format): bool
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            return false;
        }

        try {
            return \Imagick::queryFormats(strtoupper($format)) !== [];
        } catch (\ImagickException) {
            return false;
        }
    }
}
