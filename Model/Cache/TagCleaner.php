<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\CacheContextFactory;

/**
 * Cleans cache entries by tag everywhere they may live: the application caches (block HTML and the like) and the
 * page caches, the built-in full page cache as well as an HTTP accelerator.
 *
 * The page caches are reached through the framework's `clean_cache_by_tags` event with a cache context carrying the
 * tags, the same route an indexer takes, so whichever page cache is configured purges them.
 */
class TagCleaner
{
    public const EVENT_NAME = 'clean_cache_by_tags';

    /**
     * @param CacheContextFactory $cacheContextFactory
     * @param ManagerInterface $eventManager
     * @param CacheInterface $cache
     */
    public function __construct(
        private readonly CacheContextFactory $cacheContextFactory,
        private readonly ManagerInterface $eventManager,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Clean every cache entry carrying any of the tags; an empty list cleans nothing
     *
     * @param list<string> $tags
     * @return void
     */
    public function clean(array $tags): void
    {
        $tags = array_values(array_unique(array_filter($tags, fn (string $tag): bool => $tag !== '')));
        if ($tags === []) {
            return;
        }

        $cacheContext = $this->cacheContextFactory->create();
        $cacheContext->registerTags($tags);
        $this->eventManager->dispatch(self::EVENT_NAME, ['object' => $cacheContext]);
        $this->cache->clean($tags);
    }
}
