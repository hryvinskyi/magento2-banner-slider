<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyMediaPathNormaliser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyMediaPathNormaliser::class)]
class LegacyMediaPathNormaliserTest extends TestCase
{
    /**
     * @var LegacyMediaPathNormaliser
     */
    private LegacyMediaPathNormaliser $normaliser;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->normaliser = new LegacyMediaPathNormaliser();
    }

    /**
     * Image values become media-relative paths; paths outside the package folders are kept
     *
     * @param string $stored
     * @param string $expected
     * @return void
     */
    #[TestWith(['banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['/banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['media/banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['/media/banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['https://shop.test/media/banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['http://shop.test/pub/media/banner_slider/responsive/7/desktop_ab.webp',
        'banner_slider/responsive/7/desktop_ab.webp'])]
    #[TestWith(['//cdn.shop.test/media/banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['previous_slider/image/banner.png', 'previous_slider/image/banner.png'])]
    #[TestWith(['theme_assets/banner_slider/home.jpg', 'theme_assets/banner_slider/home.jpg'])]
    #[TestWith([' wysiwyg/banner.png ', 'wysiwyg/banner.png'])]
    public function testNormalise(string $stored, string $expected): void
    {
        self::assertSame($expected, $this->normaliser->normalise($stored));
    }

    /**
     * A normalised path normalises to itself
     *
     * @param string $path
     * @return void
     */
    #[TestWith(['banner_slider/image/a.jpg'])]
    #[TestWith(['previous_slider/image/remote.png'])]
    public function testNormaliseKeepsNormalisedPath(string $path): void
    {
        self::assertSame($path, $this->normaliser->normalise($path));
    }

    /**
     * Values that cannot be safe media-relative paths are rejected
     *
     * @param string $stored
     * @return void
     */
    #[TestWith([''])]
    #[TestWith(['/'])]
    #[TestWith(['../app/etc/env.php'])]
    #[TestWith(['banner_slider/../../app/etc/env.php'])]
    #[TestWith(['https://shop.test/media/../app/etc/env.php'])]
    #[TestWith(["banner_slider/image/a.jpg\0.php"])]
    #[TestWith(['banner_slider\\image\\a.jpg'])]
    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['https://other.test/images/a.jpg'])]
    #[TestWith(['//other.test/images/a.jpg'])]
    public function testNormaliseRejectsUnsafeValues(string $stored): void
    {
        self::assertNull($this->normaliser->normalise($stored));
    }

    /**
     * With the site's base URLs given, only a media URL on one of their hosts is rewritten
     *
     * @param string $stored
     * @param string|null $expected
     * @return void
     */
    #[TestWith(['https://shop.test/media/banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['http://SHOP.test:8080/pub/media/banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['//user@cdn.shop.test/media/banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['https://other.test/media/banner_slider/image/a.jpg', null])]
    #[TestWith(['https://shop.test.evil.test/media/banner_slider/image/a.jpg', null])]
    #[TestWith(['/media/banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    public function testNormaliseOnSiteHostsOnly(string $stored, ?string $expected): void
    {
        $baseUrls = ['https://shop.test/', 'https://cdn.shop.test/media/'];

        self::assertSame($expected, $this->normaliser->normalise($stored, $baseUrls));
    }

    /**
     * A video URL into media is rewritten on the site's hosts only
     *
     * @return void
     */
    public function testNormaliseVideoPathOnSiteHostsOnly(): void
    {
        $baseUrls = ['https://shop.test/'];

        self::assertSame(
            'banner_slider/video/a.mp4',
            $this->normaliser->normaliseVideoPath('https://shop.test/media/banner_slider/video/a.mp4', $baseUrls)
        );
        self::assertNull(
            $this->normaliser->normaliseVideoPath('https://other.test/media/banner_slider/video/a.mp4', $baseUrls)
        );
    }

    /**
     * Local video values are placed where the legacy storefront looked for them
     *
     * @param string $stored
     * @param string $expected
     * @return void
     */
    #[TestWith(['promo.mp4', 'banner_slider/video/promo.mp4'])]
    #[TestWith(['/promo.mp4', 'banner_slider/video/promo.mp4'])]
    #[TestWith(['banner_slider/video/promo.mp4', 'banner_slider/video/promo.mp4'])]
    #[TestWith(['/banner_slider/video/promo.mp4', 'banner_slider/video/promo.mp4'])]
    #[TestWith(['clips/promo.webm', 'banner_slider/video/clips/promo.webm'])]
    #[TestWith(['https://shop.test/media/banner_slider/video/promo.mp4', 'banner_slider/video/promo.mp4'])]
    public function testNormaliseVideoPath(string $stored, string $expected): void
    {
        self::assertSame($expected, $this->normaliser->normaliseVideoPath($stored));
    }

    /**
     * Video values that cannot be safe media-relative paths are rejected
     *
     * @param string $stored
     * @return void
     */
    #[TestWith([''])]
    #[TestWith(['../promo.mp4'])]
    #[TestWith(['https://videos.test/promo.mp4'])]
    #[TestWith(['clips\\promo.mp4'])]
    public function testNormaliseVideoPathRejectsUnsafeValues(string $stored): void
    {
        self::assertNull($this->normaliser->normaliseVideoPath($stored));
    }
}
