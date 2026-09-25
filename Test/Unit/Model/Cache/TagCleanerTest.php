<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Cache;

use Hryvinskyi\BannerSlider\Model\Cache\TagCleaner;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\CacheContextFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TagCleaner::class)]
class TagCleanerTest extends TestCase
{
    /**
     * @var ManagerInterface&MockObject
     */
    private MockObject $eventManager;

    /**
     * @var CacheInterface&MockObject
     */
    private MockObject $cache;

    /**
     * @var TagCleaner
     */
    private TagCleaner $cleaner;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $factory = $this->createMock(CacheContextFactory::class);
        $factory->method('create')->willReturnCallback(fn (): CacheContext => new CacheContext());
        $this->eventManager = $this->createMock(ManagerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cleaner = new TagCleaner($factory, $this->eventManager, $this->cache);
    }

    /**
     * The page caches get a cache context with the tags, the application cache the tags themselves
     *
     * @return void
     */
    public function testCleansPageAndApplicationCaches(): void
    {
        $tags = ['hryvinskyi_banner_slider_4', 'hryvinskyi_banner_slider_location_home'];
        $this->eventManager->expects(self::once())
            ->method('dispatch')
            ->with(
                'clean_cache_by_tags',
                self::callback(function (array $data) use ($tags): bool {
                    $context = $data['object'] ?? null;

                    return $context instanceof CacheContext && array_values($context->getIdentities()) === $tags;
                })
            );
        $this->cache->expects(self::once())
            ->method('clean')
            ->with($tags);

        $this->cleaner->clean([
            'hryvinskyi_banner_slider_4',
            '',
            'hryvinskyi_banner_slider_location_home',
            'hryvinskyi_banner_slider_4',
        ]);
    }

    /**
     * No tag, nothing cleaned
     *
     * @return void
     */
    public function testNoTagsCleanNothing(): void
    {
        $this->eventManager->expects(self::never())->method('dispatch');
        $this->cache->expects(self::never())->method('clean');

        $this->cleaner->clean(['']);
    }
}
