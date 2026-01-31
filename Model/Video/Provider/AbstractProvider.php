<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Video\Provider;

use Hryvinskyi\BannerSliderApi\Api\Video\ProviderInterface;

/**
 * Abstract video provider
 */
abstract class AbstractProvider implements ProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getEmbedAttributes(): array
    {
        return [
            'frameborder' => '0',
            'allowfullscreen' => 'allowfullscreen',
        ];
    }

    /**
     * @inheritDoc
     */
    public function isLocal(): bool
    {
        return false;
    }
}
