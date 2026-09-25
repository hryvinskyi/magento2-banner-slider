<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Video;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\Video\Provider\LocalFile;
use Hryvinskyi\BannerSlider\Model\Video\Provider\Vimeo;
use Hryvinskyi\BannerSlider\Model\Video\Provider\YouTube;
use Hryvinskyi\BannerSlider\Model\Video\ProviderResolver;
use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderResolver::class)]
class ProviderResolverTest extends TestCase
{
    /**
     * With several providers supporting a source, the highest priority wins whatever the pool order
     *
     * @return void
     */
    public function testHighestPriorityWins(): void
    {
        $low = $this->provider('low', 10, true);
        $high = $this->provider('high', 50, true);
        $unsupported = $this->provider('none', 100, false);

        self::assertSame($high, (new ProviderResolver([$low, $unsupported, $high]))->resolve('x'));
    }

    /**
     * Equal priorities keep the pool order
     *
     * @return void
     */
    public function testEqualPriorityKeepsPoolOrder(): void
    {
        $first = $this->provider('first', 10, true);
        $second = $this->provider('second', 10, true);

        self::assertSame($first, (new ProviderResolver(['a' => $first, 'b' => $second]))->resolve('x'));
    }

    /**
     * No provider supporting the source gives null
     *
     * @return void
     */
    public function testNoProvider(): void
    {
        self::assertNull((new ProviderResolver([$this->provider('none', 1, false)]))->resolve('x'));
        self::assertNull((new ProviderResolver())->resolve('x'));
    }

    /**
     * The shipped pool routes each source to its provider
     *
     * @param string $source
     * @param string|null $code
     * @return void
     */
    #[TestWith(['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube'])]
    #[TestWith(['https://youtu.be/dQw4w9WgXcQ', 'youtube'])]
    #[TestWith(['https://vimeo.com/76979871', 'vimeo'])]
    #[TestWith(['banner_slider/video/2026/09/promo-0123456789ab.mp4', 'local_mp4'])]
    #[TestWith(['banner_slider/video/promo.webm', 'local_webm'])]
    #[TestWith(['banner_slider/video/promo.ogv', null])]
    #[TestWith(['https://example.test/video.mp4', null])]
    public function testShippedPool(string $source, ?string $code): void
    {
        $urls = $this->createMock(MediaUrlResolverInterface::class);
        $paths = new MediaPaths(['video' => 'banner_slider/video']);
        $resolver = new ProviderResolver([
            'youtube' => new YouTube(),
            'vimeo' => new Vimeo(),
            'local_mp4' => new LocalFile($urls, $paths, 'local_mp4', 'mp4'),
            'local_webm' => new LocalFile($urls, $paths, 'local_webm', 'webm'),
        ]);

        self::assertSame($code, $resolver->resolve($source)?->getCode());
    }

    /**
     * A pool entry that is not a provider fails loudly
     *
     * @return void
     */
    public function testRejectsNonProvider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"broken"');

        new ProviderResolver(['broken' => new \stdClass()]);
    }

    /**
     * Two providers with one code fail loudly
     *
     * @return void
     */
    public function testRejectsDuplicateCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProviderResolver([$this->provider('same', 1, true), $this->provider('same', 2, true)]);
    }

    /**
     * A provider stub
     *
     * @param string $code
     * @param int $priority
     * @param bool $supports
     * @return ProviderInterface
     */
    private function provider(string $code, int $priority, bool $supports): ProviderInterface
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getCode')->willReturn($code);
        $provider->method('getPriority')->willReturn($priority);
        $provider->method('supports')->willReturn($supports);

        return $provider;
    }
}
