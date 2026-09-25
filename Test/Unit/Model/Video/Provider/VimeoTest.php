<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Video\Provider;

use Hryvinskyi\BannerSlider\Model\Video\Provider\Vimeo;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedKind;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedOptions;
use Hryvinskyi\BannerSliderApi\Api\Value\VideoData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Vimeo::class)]
class VimeoTest extends TestCase
{
    /**
     * Every supported URL shape yields the numeric video id
     *
     * @param string $source
     * @param string $videoId
     * @return void
     */
    #[TestWith(['https://vimeo.com/76979871', '76979871'])]
    #[TestWith(['https://www.vimeo.com/76979871', '76979871'])]
    #[TestWith(['vimeo.com/76979871', '76979871'])]
    #[TestWith(['https://vimeo.com/76979871/', '76979871'])]
    #[TestWith(['https://vimeo.com/76979871?share=copy', '76979871'])]
    #[TestWith(['https://vimeo.com/76979871/abcdef1234', '76979871'])]
    #[TestWith(['https://player.vimeo.com/video/76979871', '76979871'])]
    #[TestWith(['https://player.vimeo.com/video/76979871?h=abcdef1234&badge=0', '76979871'])]
    #[TestWith(['https://player.vimeo.com/video/76979871?autoplay=1', '76979871'])]
    public function testParsesSupportedShapes(string $source, string $videoId): void
    {
        $provider = new Vimeo();

        self::assertTrue($provider->supports($source));
        $data = $provider->parse($source);
        self::assertSame('vimeo', $data->getProviderCode());
        self::assertSame($videoId, $data->getVideoId());
        self::assertSame($source, $data->getSourceUrl());
    }

    /**
     * Other hosts, non-numeric ids and other sources are not Vimeo videos
     *
     * @param string $source
     * @return void
     */
    #[TestWith(['https://vimeo.com/channels/staffpicks'])]
    #[TestWith(['https://vimeo.com/abc'])]
    #[TestWith(['https://vimeo.com/123abc'])]
    #[TestWith(['https://vimeo.com.evil.test/76979871'])]
    #[TestWith(['https://notvimeo.com/76979871'])]
    #[TestWith(['https://player.vimeo.com/76979871'])]
    #[TestWith(['https://youtu.be/dQw4w9WgXcQ'])]
    #[TestWith([''])]
    public function testRejectsOtherSources(string $source): void
    {
        $provider = new Vimeo();

        self::assertFalse($provider->supports($source));
        $this->expectException(\InvalidArgumentException::class);
        $provider->parse($source);
    }

    /**
     * The embed URL carries the unlisted hash and follows the options
     *
     * @param string $source
     * @param bool $background
     * @param bool $privacy
     * @param string $expected
     * @return void
     */
    #[TestWith(['https://vimeo.com/76979871', true, true,
        'https://player.vimeo.com/video/76979871?background=1&autoplay=1&muted=1&loop=1&dnt=1'])]
    #[TestWith(['https://vimeo.com/76979871', false, true, 'https://player.vimeo.com/video/76979871?dnt=1'])]
    #[TestWith(['https://vimeo.com/76979871', false, false, 'https://player.vimeo.com/video/76979871'])]
    #[TestWith(['https://vimeo.com/76979871/ABCDEF1234', false, false,
        'https://player.vimeo.com/video/76979871?h=abcdef1234'])]
    #[TestWith(['https://player.vimeo.com/video/76979871?h=abcdef1234', true, false,
        'https://player.vimeo.com/video/76979871?h=abcdef1234&background=1&autoplay=1&muted=1&loop=1'])]
    public function testEmbedUrl(string $source, bool $background, bool $privacy, string $expected): void
    {
        $provider = new Vimeo();
        $options = new EmbedOptions(
            background: $background,
            autoplay: $background,
            muted: $background,
            loop: $background,
            controls: !$background,
            privacyEnhanced: $privacy
        );

        self::assertSame($expected, $provider->getEmbedUrl($provider->parse($source), $options));
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
        $provider = new Vimeo();
        $options = new EmbedOptions(
            background: $background,
            autoplay: $background,
            muted: $background,
            loop: $background,
            controls: !$background,
            privacyEnhanced: false
        );

        self::assertSame(EmbedKind::IFRAME, $provider->getEmbedKind());
        self::assertSame(
            [
                'allow' => 'autoplay; fullscreen; picture-in-picture',
                'allowfullscreen' => $fullscreen,
                'referrerpolicy' => 'strict-origin-when-cross-origin',
            ],
            $provider->getEmbedAttributes($provider->parse('https://vimeo.com/76979871'), $options)
        );
    }

    /**
     * Code and priority identify the provider in the pool
     *
     * @return void
     */
    public function testCodeAndPriority(): void
    {
        self::assertSame('vimeo', (new Vimeo())->getCode());
        self::assertSame(90, (new Vimeo())->getPriority());
    }

    /**
     * Video data of another provider, or with a non-numeric id, is refused
     *
     * @param string $providerCode
     * @param string $videoId
     * @return void
     */
    #[TestWith(['youtube', '76979871'])]
    #[TestWith(['vimeo', '7697"onload'])]
    public function testRefusesForeignData(string $providerCode, string $videoId): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Vimeo())->getEmbedAttributes(
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
