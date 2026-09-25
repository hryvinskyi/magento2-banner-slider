<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Magento\Framework\UrlInterface;

/**
 * Public URLs of media files on the current store's media base URL.
 *
 * Only the safe-path rule applies here: a stored path outside the package's own folders (rows written by earlier
 * imports point at other media folders) still gets its URL, while a path that could leave the media directory is
 * refused. Each path segment is percent-encoded, so a file name with a space, `#`, `?` or `%` still names that file.
 */
class MediaUrlResolver implements MediaUrlResolverInterface
{
    /**
     * @param UrlInterface $urlBuilder
     * @param MediaPaths $mediaPaths
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly MediaPaths $mediaPaths
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getUrl(string $relativePath): string
    {
        $path = $this->mediaPaths->assertSafe($relativePath);
        $baseUrl = $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA]);

        return rtrim($baseUrl, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
