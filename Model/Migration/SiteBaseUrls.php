<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The base URLs of every store view: web, link and media, unsecure and secure, as configured.
 *
 * The migration uses them to tell a stored URL into this site's media apart from a URL on another host. They are read
 * from the configuration rather than built by the store: building a static URL needs the deployed static version,
 * which a deployment may not have yet when the migration runs (`setup:upgrade` deletes it). A static URL is never
 * where media lives, so it is not needed. A value still holding a `{{…}}` placeholder names a base URL already
 * listed.
 */
class SiteBaseUrls
{
    private const PATHS = [
        'web/unsecure/base_url',
        'web/secure/base_url',
        'web/unsecure/base_link_url',
        'web/secure/base_link_url',
        'web/unsecure/base_media_url',
        'web/secure/base_media_url',
    ];

    /**
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
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
        foreach ($this->storeManager->getStores(true) as $store) {
            foreach (self::PATHS as $path) {
                $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $store->getId());
                $url = is_string($value) ? trim($value) : '';
                if ($url !== '' && !str_contains($url, '{{')) {
                    $urls[$url] = true;
                }
            }
        }

        return array_map('strval', array_keys($urls));
    }
}
