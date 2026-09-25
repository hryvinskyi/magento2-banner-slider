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
 * Picks the video provider for a source from the provider pool in `di.xml`.
 *
 * Providers are asked in priority order (highest first; equal priorities keep their pool order), and the first one
 * that supports the source wins. A pool entry that is not a provider, or two providers with one code, fail loudly
 * when the resolver is built.
 */
class ProviderResolver implements ProviderResolverInterface
{
    /**
     * @var list<ProviderInterface>
     */
    private readonly array $providers;

    /**
     * @param array<array-key,mixed> $providers Provider pool
     * @throws \InvalidArgumentException When an entry is not a provider or a provider code is used twice
     */
    public function __construct(array $providers = [])
    {
        $byCode = [];
        foreach ($providers as $name => $provider) {
            if (!$provider instanceof ProviderInterface) {
                throw new \InvalidArgumentException(
                    sprintf('The video provider pool entry "%s" is not a video provider.', $name)
                );
            }
            if (isset($byCode[$provider->getCode()])) {
                throw new \InvalidArgumentException(
                    sprintf('Two video providers use the code "%s".', $provider->getCode())
                );
            }
            $byCode[$provider->getCode()] = $provider;
        }
        $ordered = array_values($byCode);
        usort(
            $ordered,
            static fn (ProviderInterface $a, ProviderInterface $b): int => $b->getPriority() <=> $a->getPriority()
        );
        $this->providers = $ordered;
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $source): ?ProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($source)) {
                return $provider;
            }
        }

        return null;
    }
}
