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
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;

/**
 * A custom-content banner is saved with content.
 */
class ContentRequired implements BannerRuleInterface
{
    /**
     * @inheritDoc
     */
    public function validate(BannerInterface $banner): array
    {
        if ($banner->getType() !== BannerType::CUSTOM || trim($banner->getContent() ?? '') !== '') {
            return [];
        }

        return [__('Enter the content of the custom banner.')];
    }
}
