<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule\Banner;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\BannerRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;

/**
 * A banner whose type shows an image is saved with an image.
 */
class ImageRequired implements BannerRuleInterface
{
    /**
     * @inheritDoc
     */
    public function validate(BannerInterface $banner): array
    {
        if (!$banner->getType()->requiresImage() || $banner->getImage() !== null) {
            return [];
        }

        return [__('Upload or choose an image for the image banner.')];
    }
}
