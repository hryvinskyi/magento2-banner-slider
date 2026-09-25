<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

use Magento\Store\Api\StoreConfigManagerInterface;

/**
 * The base URLs of every store view: web, link, media and static, unsecure and secure.
 *
 * The migration uses them to tell a stored URL into this site's media apart from a URL on another host.
 */
class SiteBaseUrls
{
    /**
     * @param StoreConfigManagerInterface $storeConfigManager
     */
    public function __construct(
        private readonly StoreConfigManagerInterface $storeConfigManager
    ) {
    }

    /**
     * Every distinct, non-empty base URL of the store views
     *
     * @return list<string>
     */
    public function getAll(): array
    {
        $urls = [];
        foreach ($this->storeConfigManager->getStoreConfigs() as $config) {
            $candidates = [
                $config->getBaseUrl(),
                $config->getSecureBaseUrl(),
                $config->getBaseLinkUrl(),
                $config->getSecureBaseLinkUrl(),
                $config->getBaseMediaUrl(),
                $config->getSecureBaseMediaUrl(),
                $config->getBaseStaticUrl(),
                $config->getSecureBaseStaticUrl(),
            ];
            foreach ($candidates as $url) {
                $url = trim((string)$url);
                if ($url !== '') {
                    $urls[$url] = true;
                }
            }
        }

        return array_map('strval', array_keys($urls));
    }
}
