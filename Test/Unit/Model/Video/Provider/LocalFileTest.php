<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Video\Provider;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\Video\Provider\LocalFile;
use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedKind;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedOptions;
use Hryvinskyi\BannerSliderApi\Api\Value\VideoData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalFile::class)]
class LocalFileTest extends TestCase
{
    /**
     * A media path in the video folder with the provider's extension is supported; its id is the path
     *
     * @param string $source
     * @param string $path
     * @return void
     */
    #[TestWith(['banner_slider/video/promo.mp4', 'banner_slider/video/promo.mp4'])]
    #[TestWith(['banner_slider/video/2026/09/p-0123456789ab.mp4', 'banner_slider/video/2026/09/p-0123456789ab.mp4'])]
    #[TestWith(['banner_slider/video/PROMO.MP4', 'banner_slider/video/PROMO.MP4'])]
    #[TestWith([' banner_slider/video/promo.mp4 ', 'banner_slider/video/promo.mp4'])]
    public function testParsesVideoPath(string $source, string $path): void
    {
        $provider = $this->provider();

        self::assertTrue($provider->supports($source));
        $data = $provider->parse($source);
        self::assertSame('local_mp4', $data->getProviderCode());
        self::assertSame($path, $data->getVideoId());
    }

    /**
     * Other extensions, other folders, bare names, URLs and unsafe paths are not this provider's
     *
     * @param string $source
     * @return void
     */
    #[TestWith(['banner_slider/video/promo.webm'])]
    #[TestWith(['banner_slider/video/promo.mp4.txt'])]
    #[TestWith(['banner_slider/video/.mp4'])]
    #[TestWith(['banner_slider/video/'])]
    #[TestWith(['banner_slider/image/promo.mp4'])]
    #[TestWith(['banner_slider/videos/promo.mp4'])]
    #[TestWith(['promo.mp4'])]
    #[TestWith(['/banner_slider/video/promo.mp4'])]
    #[TestWith(['banner_slider/video/../../app/etc/promo.mp4'])]
    #[TestWith(['https://cdn.test/banner_slider/video/promo.mp4'])]
    #[TestWith(['https://youtu.be/dQw4w9WgXcQ'])]
    #[TestWith([''])]
    public function testRejectsOtherSources(string $source): void
    {
        $provider = $this->provider();

        self::assertFalse($provider->supports($source));
        $this->expectException(\InvalidArgumentException::class);
        $provider->parse($source);
    }

    /**
     * The embed URL is the media URL of the stored path
     *
     * @return void
     */
    public function testEmbedUrlIsMediaUrl(): void
    {
        $urls = $this->createMock(MediaUrlResolverInterface::class);
        $urls->expects(self::once())->method('getUrl')->with('banner_slider/video/promo.mp4')
            ->willReturn('https://shop.test/media/banner_slider/video/promo.mp4');
        $provider = $this->provider($urls);

        self::assertSame(EmbedKind::VIDEO, $provider->getEmbedKind());
        self::assertSame(
            'https://shop.test/media/banner_slider/video/promo.mp4',
            $provider->getEmbedUrl($provider->parse('banner_slider/video/promo.mp4'), $this->options(true))
        );
    }

    /**
     * The video element attributes follow the options; playback is always inline and loads metadata only
     *
     * @param bool $background
     * @return void
     */
    #[TestWith([true])]
    #[TestWith([false])]
    public function testAttributes(bool $background): void
    {
        $provider = $this->provider();

        self::assertSame(
            [
                'autoplay' => $background,
                'muted' => $background,
                'loop' => $background,
                'playsinline' => true,
                'controls' => !$background,
                'preload' => 'metadata',
            ],
            $provider->getEmbedAttributes($provider->parse('banner_slider/video/a.mp4'), $this->options($background))
        );
    }

    /**
     * Code and priority come from the virtual type's arguments
     *
     * @return void
     */
    public function testCodeAndPriority(): void
    {
        self::assertSame('local_mp4', $this->provider()->getCode());
        self::assertSame(10, $this->provider()->getPriority());
    }

    /**
     * Video data of another provider or type is refused
     *
     * @param string $providerCode
     * @param string $videoId
     * @return void
     */
    #[TestWith(['local_webm', 'banner_slider/video/a.mp4'])]
    #[TestWith(['local_mp4', 'banner_slider/video/a.webm'])]
    #[TestWith(['local_mp4', '../a.mp4'])]
    public function testRefusesForeignData(string $providerCode, string $videoId): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->provider()->getEmbedUrl(new VideoData($providerCode, $videoId, $videoId), $this->options(false));
    }

    /**
     * A provider without a code, or with an extension that is not lowercase letters or digits, is not built
     *
     * @param string $code
     * @param string $extension
     * @return void
     */
    #[TestWith(['', 'mp4'])]
    #[TestWith(['local_mp4', '.mp4'])]
    #[TestWith(['local_mp4', 'MP4'])]
    public function testRejectsBrokenArguments(string $code, string $extension): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LocalFile($this->createMock(MediaUrlResolverInterface::class), $this->paths(), $code, $extension);
    }

    /**
     * The MP4 provider as `di.xml` declares it
     *
     * @param MediaUrlResolverInterface|null $urls
     * @return LocalFile
     */
    private function provider(?MediaUrlResolverInterface $urls = null): LocalFile
    {
        return new LocalFile(
            $urls ?? $this->createMock(MediaUrlResolverInterface::class),
            $this->paths(),
            'local_mp4',
            'mp4'
        );
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

    /**
     * The background or the player option set
     *
     * @param bool $background
     * @return EmbedOptions
     */
    private function options(bool $background): EmbedOptions
    {
        return new EmbedOptions(
            background: $background,
            autoplay: $background,
            muted: $background,
            loop: $background,
            controls: !$background,
            privacyEnhanced: true
        );
    }
}
