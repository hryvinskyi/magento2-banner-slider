<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\Media\MediaUrlResolver;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaUrlResolver::class)]
class MediaUrlResolverTest extends TestCase
{
    /**
     * The media base URL and the path are joined with exactly one slash
     *
     * @param string $baseUrl
     * @return void
     */
    #[TestWith(['https://shop.test/media/'])]
    #[TestWith(['https://shop.test/media'])]
    #[TestWith(['https://shop.test/media//'])]
    public function testJoinsWithOneSlash(string $baseUrl): void
    {
        self::assertSame(
            'https://shop.test/media/banner_slider/image/2026/09/a-0123456789ab.jpg',
            $this->resolver($baseUrl)->getUrl('banner_slider/image/2026/09/a-0123456789ab.jpg')
        );
    }

    /**
     * The media base URL is asked for with the media URL type
     *
     * @return void
     */
    public function testAsksForMediaBaseUrl(): void
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->expects(self::once())->method('getBaseUrl')
            ->with(['_type' => UrlInterface::URL_TYPE_MEDIA])
            ->willReturn('https://cdn.test/m/');

        self::assertSame(
            'https://cdn.test/m/banner_slider/video/a.mp4',
            (new MediaUrlResolver($urlBuilder, $this->paths()))->getUrl('banner_slider/video/a.mp4')
        );
    }

    /**
     * Stored paths outside the package folders still resolve, because earlier imports wrote them
     *
     * @param string $path
     * @return void
     */
    #[TestWith(['legacy_slider/image/slide.jpg'])]
    #[TestWith(['hryvinskyi/banner_slider/slide.webp'])]
    #[TestWith(['wysiwyg/banner-1_a~b.png'])]
    public function testAcceptsSafePathOutsideRoots(string $path): void
    {
        self::assertSame(
            'https://shop.test/media/' . $path,
            $this->resolver('https://shop.test/media/')->getUrl($path)
        );
    }

    /**
     * Each path segment is percent-encoded, so the URL names exactly the stored file
     *
     * @param string $path
     * @param string $expected
     * @return void
     */
    #[TestWith(['wysiwyg/banner with space.png', 'wysiwyg/banner%20with%20space.png'])]
    #[TestWith(['banner_slider/image/sale#1.jpg', 'banner_slider/image/sale%231.jpg'])]
    #[TestWith(['banner_slider/image/50%off?.jpg', 'banner_slider/image/50%25off%3F.jpg'])]
    #[TestWith(['legacy/café.jpg', 'legacy/caf%C3%A9.jpg'])]
    public function testEncodesEachSegment(string $path, string $expected): void
    {
        self::assertSame(
            'https://shop.test/media/' . $expected,
            $this->resolver('https://shop.test/media/')->getUrl($path)
        );
    }

    /**
     * A path that could leave the media directory is refused
     *
     * @param string $path
     * @return void
     */
    #[TestWith(['../app/etc/env.php'])]
    #[TestWith(['banner_slider/../../app/etc/env.php'])]
    #[TestWith(['/etc/passwd'])]
    #[TestWith(['https://evil.test/a.jpg'])]
    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(["banner_slider/a\0.jpg"])]
    #[TestWith(['banner_slider\\a.jpg'])]
    #[TestWith([''])]
    public function testRejectsUnsafePath(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver('https://shop.test/media/')->getUrl($path);
    }

    /**
     * A resolver on a fixed media base URL
     *
     * @param string $baseUrl
     * @return MediaUrlResolver
     */
    private function resolver(string $baseUrl): MediaUrlResolver
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getBaseUrl')->willReturn($baseUrl);

        return new MediaUrlResolver($urlBuilder, $this->paths());
    }

    /**
     * The package media roots
     *
     * @return MediaPaths
     */
    private function paths(): MediaPaths
    {
        return new MediaPaths(['image' => 'banner_slider/image', 'video' => 'banner_slider/video']);
    }
}
