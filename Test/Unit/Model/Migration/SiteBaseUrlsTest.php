<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Migration\SiteBaseUrls;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The site's base URLs come from the configuration of each store view, never from building a static URL
 */
#[CoversClass(SiteBaseUrls::class)]
class SiteBaseUrlsTest extends TestCase
{
    /**
     * @return void
     */
    public function testCollectsTheDistinctConfiguredBaseUrlsOfEveryStoreView(): void
    {
        $urls = $this->siteBaseUrls([
            0 => $this->store('https://shop.test/', '{{unsecure_base_url}}media/'),
            1 => $this->store('https://shop.test/', 'https://cdn.shop.test/media/'),
            2 => $this->store(' https://de.shop.test/ ', ''),
        ]);

        self::assertSame(
            ['https://shop.test/', 'https://cdn.shop.test/media/', 'https://de.shop.test/'],
            $urls->getAll()
        );
    }

    /**
     * @return void
     */
    public function testNoStaticUrlIsAskedFor(): void
    {
        $asked = [];
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->with(true)->willReturn([$this->storeWithId(1)]);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path) use (&$asked): string {
                $asked[] = $path;

                return 'https://shop.test/';
            }
        );

        (new SiteBaseUrls($storeManager, $scopeConfig))->getAll();

        self::assertNotEmpty($asked);
        foreach ($asked as $path) {
            self::assertStringNotContainsString('static', $path);
        }
    }

    /**
     * SiteBaseUrls over store views with the given web and media URLs (the link URLs equal the web URL)
     *
     * @param array<int, array{web: string, media: string}> $stores Store id => its URLs
     * @return SiteBaseUrls
     */
    private function siteBaseUrls(array $stores): SiteBaseUrls
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->with(true)->willReturn(
            array_map(fn (int $id): StoreInterface => $this->storeWithId($id), array_keys($stores))
        );
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path, string $scope, int|string|null $storeId) use ($stores): string {
                self::assertSame(ScopeInterface::SCOPE_STORE, $scope);
                $urls = $stores[(int)$storeId];

                return str_contains($path, 'media') ? $urls['media'] : $urls['web'];
            }
        );

        return new SiteBaseUrls($storeManager, $scopeConfig);
    }

    /**
     * The URLs of one store view
     *
     * @param string $web
     * @param string $media
     * @return array{web: string, media: string}
     */
    private function store(string $web, string $media): array
    {
        return ['web' => $web, 'media' => $media];
    }

    /**
     * A store view with the given id
     *
     * @param int $id
     * @return StoreInterface
     */
    private function storeWithId(int $id): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);

        return $store;
    }
}
