<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Migration\SiteBaseUrls;
use Magento\Store\Api\Data\StoreConfigInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteBaseUrls::class)]
class SiteBaseUrlsTest extends TestCase
{
    /**
     * Every base URL of every store view, once, without blanks
     *
     * @return void
     */
    public function testCollectsDistinctBaseUrls(): void
    {
        $manager = $this->createMock(StoreConfigManagerInterface::class);
        $manager->method('getStoreConfigs')->willReturn([
            $this->config('https://shop.test/', 'https://cdn.shop.test/media/'),
            $this->config('https://de.shop.test/', ''),
        ]);

        self::assertSame(
            ['https://shop.test/', 'https://cdn.shop.test/media/', 'https://de.shop.test/'],
            (new SiteBaseUrls($manager))->getAll()
        );
    }

    /**
     * A store view configuration whose web, link and static URLs are one URL and whose media URL is another
     *
     * @param string $webUrl
     * @param string $mediaUrl
     * @return StoreConfigInterface
     */
    private function config(string $webUrl, string $mediaUrl): StoreConfigInterface
    {
        $config = $this->createMock(StoreConfigInterface::class);
        foreach (['getBaseUrl', 'getSecureBaseUrl', 'getBaseLinkUrl', 'getSecureBaseLinkUrl'] as $method) {
            $config->method($method)->willReturn($webUrl);
        }
        foreach (['getBaseStaticUrl', 'getSecureBaseStaticUrl'] as $method) {
            $config->method($method)->willReturn(' ' . $webUrl);
        }
        foreach (['getBaseMediaUrl', 'getSecureBaseMediaUrl'] as $method) {
            $config->method($method)->willReturn($mediaUrl);
        }

        return $config;
    }
}
