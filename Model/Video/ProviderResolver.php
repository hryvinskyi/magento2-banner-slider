<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Video;

use Hryvinskyi\BannerSliderApi\Api\Video\ProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderResolverInterface;

/**
 * Video provider resolver
 */
class ProviderResolver implements ProviderResolverInterface
{
    /**
     * @var ProviderInterface[]
     */
    private array $providersByCode = [];

    /**
     * @param ProviderInterface[] $providers
     */
    public function __construct(
        private readonly array $providers = []
    ) {
        foreach ($this->providers as $provider) {
            $this->providersByCode[$provider->getCode()] = $provider;
        }
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $url): ?ProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($url)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getByCode(string $code): ?ProviderInterface
    {
        return $this->providersByCode[$code] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        return $this->providers;
    }
}
