<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Video\Provider;

use Hryvinskyi\BannerSlider\Model\Video\Provider\YouTube;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedKind;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedOptions;
use Hryvinskyi\BannerSliderApi\Api\Value\VideoData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(YouTube::class)]
class YouTubeTest extends TestCase
{
    private const ID = 'dQw4w9WgXcQ';

    /**
     * Every supported URL shape yields the video id and keeps the source
     *
     * @param string $source
     * @return void
     */
    #[TestWith(['https://www.youtube.com/watch?v=dQw4w9WgXcQ'])]
    #[TestWith(['https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42'])]
    #[TestWith(['https://m.youtube.com/watch?v=dQw4w9WgXcQ'])]
    #[TestWith(['http://youtube.com/watch?v=dQw4w9WgXcQ#t=10'])]
    #[TestWith(['youtube.com/watch?v=dQw4w9WgXcQ'])]
    #[TestWith(['https://WWW.YouTube.com/watch?v=dQw4w9WgXcQ'])]
    #[TestWith(['https://youtu.be/dQw4w9WgXcQ'])]
    #[TestWith(['https://youtu.be/dQw4w9WgXcQ?si=abc&t=5'])]
    #[TestWith(['https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0'])]
    #[TestWith(['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'])]
    #[TestWith(['https://www.youtube.com/shorts/dQw4w9WgXcQ'])]
    #[TestWith(['https://m.youtube.com/shorts/dQw4w9WgXcQ/'])]
    public function testParsesSupportedShapes(string $source): void
    {
        $provider = new YouTube();

        self::assertTrue($provider->supports($source));
        $data = $provider->parse($source);
        self::assertSame('youtube', $data->getProviderCode());
        self::assertSame(self::ID, $data->getVideoId());
        self::assertSame($source, $data->getSourceUrl());
    }

    /**
     * Surrounding whitespace is ignored
     *
     * @return void
     */
    public function testTrimsSource(): void
    {
        self::assertSame(self::ID, (new YouTube())->parse('  https://youtu.be/dQw4w9WgXcQ ')->getVideoId());
    }

    /**
     * Invalid ids, other hosts and other sources are not YouTube videos
     *
     * @param string $source
     * @return void
     */
    #[TestWith(['https://www.youtube.com/watch?v=short'])]
    #[TestWith(['https://www.youtube.com/watch?v=dQw4w9WgXcQX'])]
    #[TestWith(['https://youtu.be/dQw4w9WgXc'])]
    #[TestWith(['https://www.youtube.com/embed/dQw4w9WgXcQ%22onload'])]
    #[TestWith(['https://www.youtube.com/watch?list=PL1234567890'])]
    #[TestWith(['https://www.youtube.com/watch?vv=dQw4w9WgXcQ'])]
    #[TestWith(['https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ'])]
    #[TestWith(['https://evilyoutube.com/watch?v=dQw4w9WgXcQ'])]
    #[TestWith(['https://www.youtube-nocookie.com/watch?v=dQw4w9WgXcQ'])]
    #[TestWith(['ftp://youtu.be/dQw4w9WgXcQ'])]
    #[TestWith(['https://vimeo.com/76979871'])]
    #[TestWith(['banner_slider/video/clip.mp4'])]
    #[TestWith([''])]
    public function testRejectsOtherSources(string $source): void
    {
        $provider = new YouTube();

        self::assertFalse($provider->supports($source));
        $this->expectException(\InvalidArgumentException::class);
        $provider->parse($source);
    }

    /**
     * The embed URL follows the options: host by privacy, parameters by playback flags
     *
     * @param bool $background
     * @param bool $autoplay
     * @param bool $muted
     * @param bool $loop
     * @param bool $controls
     * @param bool $privacy
     * @param string $expected
     * @return void
     */
    #[TestWith([true, true, true, true, false, true, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1'
        . '&mute=1&loop=1&playlist=dQw4w9WgXcQ&controls=0&playsinline=1&rel=0&enablejsapi=1'])]
    #[TestWith([true, true, true, true, false, false, 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1'
        . '&mute=1&loop=1&playlist=dQw4w9WgXcQ&controls=0&playsinline=1&rel=0&enablejsapi=1'])]
    #[TestWith([false, false, false, false, true, false, 'https://www.youtube.com/embed/dQw4w9WgXcQ'
        . '?playsinline=1&rel=0&enablejsapi=1'])]
    #[TestWith([false, false, false, false, true, true, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'
        . '?playsinline=1&rel=0&enablejsapi=1'])]
    #[TestWith([false, true, true, false, true, false, 'https://www.youtube.com/embed/dQw4w9WgXcQ'
        . '?autoplay=1&mute=1&playsinline=1&rel=0&enablejsapi=1'])]
    public function testEmbedUrl(
        bool $background,
        bool $autoplay,
        bool $muted,
        bool $loop,
        bool $controls,
        bool $privacy,
        string $expected
    ): void {
        $provider = new YouTube();
        $options = new EmbedOptions(
            background: $background,
            autoplay: $autoplay,
            muted: $muted,
            loop: $loop,
            controls: $controls,
            privacyEnhanced: $privacy
        );

        self::assertSame($expected, $provider->getEmbedUrl($provider->parse('https://youtu.be/' . self::ID), $options));
    }

    /**
     * An iframe with the permissions it needs; only a player may go full screen
     *
     * @param bool $background
     * @param bool $fullscreen
     * @return void
     */
    #[TestWith([true, false])]
    #[TestWith([false, true])]
    public function testEmbedKindAndAttributes(bool $background, bool $fullscreen): void
    {
        $provider = new YouTube();
        $options = new EmbedOptions(
            background: $background,
            autoplay: $background,
            muted: $background,
            loop: $background,
            controls: !$background,
            privacyEnhanced: true
        );

        self::assertSame(EmbedKind::IFRAME, $provider->getEmbedKind());
        self::assertSame(
            [
                'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture;'
                    . ' web-share',
                'allowfullscreen' => $fullscreen,
                'referrerpolicy' => 'strict-origin-when-cross-origin',
            ],
            $provider->getEmbedAttributes($provider->parse('https://youtu.be/' . self::ID), $options)
        );
    }

    /**
     * Code and priority identify the provider in the pool
     *
     * @return void
     */
    public function testCodeAndPriority(): void
    {
        self::assertSame('youtube', (new YouTube())->getCode());
        self::assertSame(100, (new YouTube())->getPriority());
        self::assertSame(5, (new YouTube(5))->getPriority());
    }

    /**
     * Video data of another provider, or with a broken id, is refused
     *
     * @param string $providerCode
     * @param string $videoId
     * @return void
     */
    #[TestWith(['vimeo', 'dQw4w9WgXcQ'])]
    #[TestWith(['youtube', 'dQw4w9WgXcQ"><script>'])]
    public function testRefusesForeignData(string $providerCode, string $videoId): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new YouTube())->getEmbedUrl(
            new VideoData($providerCode, $videoId, 'https://example.test/'),
            new EmbedOptions(
                background: false,
                autoplay: false,
                muted: false,
                loop: false,
                controls: true,
                privacyEnhanced: true
            )
        );
    }
}
