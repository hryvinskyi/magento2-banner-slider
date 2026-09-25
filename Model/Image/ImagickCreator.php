<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

/**
 * Creates empty Imagick objects, so the classes that use one can be tested without the extension.
 *
 * Hand-written because the framework generates no factory for a class of a PHP extension.
 */
class ImagickCreator
{
    /**
     * A new, empty Imagick object
     *
     * @return \Imagick
     */
    public function create(): \Imagick
    {
        return new \Imagick();
    }
}
