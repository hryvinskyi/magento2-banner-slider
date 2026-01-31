<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSliderApi\Api\Image\ImagePathConfigInterface;

/**
 * Image path configuration
 */
class ImagePathConfig implements ImagePathConfigInterface
{
    private const RESPONSIVE_PATH = 'banner_slider/responsive';

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getResponsivePath(): string
    {
        return self::RESPONSIVE_PATH;
    }
}
