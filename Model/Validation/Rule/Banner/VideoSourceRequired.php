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
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderResolverInterface;

/**
 * A banner whose type shows a video is saved with a video URL or an uploaded video file, and a video URL is one a
 * registered video provider can play; the storefront could not render any other.
 */
class VideoSourceRequired implements BannerRuleInterface
{
    /**
     * @param ProviderResolverInterface $providerResolver
     */
    public function __construct(
        private readonly ProviderResolverInterface $providerResolver
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(BannerInterface $banner): array
    {
        if (!$banner->getType()->requiresVideo()) {
            return [];
        }
        $url = $banner->getVideoUrl();
        if ($url === null) {
            return $banner->getVideoPath() === null
                ? [__('Enter a video URL or upload a video file for the video banner.')]
                : [];
        }

        return $this->providerResolver->resolve($url) === null
            ? [__('No video provider can play "%1". Enter a supported video URL or upload a video file.', $url)]
            : [];
    }
}
