<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Validation\Rule\Banner;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\Banner\ContentRequired;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\Banner\ImageRequired;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\Banner\SliderAssigned;
use Hryvinskyi\BannerSlider\Model\Validation\Rule\Banner\VideoSourceRequired;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderResolverInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(SliderAssigned::class)]
#[CoversClass(ImageRequired::class)]
#[CoversClass(VideoSourceRequired::class)]
#[CoversClass(ContentRequired::class)]
class BannerRulesTest extends TestCase
{
    /**
     * A banner belongs to a slider
     *
     * @return void
     */
    public function testSliderAssigned(): void
    {
        self::assertSame([], (new SliderAssigned())->validate($this->banner(BannerType::IMAGE, sliderId: 2)));
        self::assertCount(1, (new SliderAssigned())->validate($this->banner(BannerType::IMAGE)));
    }

    /**
     * An image banner needs an image; other types do not
     *
     * @param BannerType $type
     * @param string|null $image
     * @param int $errors
     * @return void
     */
    #[TestWith([BannerType::IMAGE, null, 1])]
    #[TestWith([BannerType::IMAGE, 'banner_slider/image/a.jpg', 0])]
    #[TestWith([BannerType::VIDEO, null, 0])]
    #[TestWith([BannerType::CUSTOM, null, 0])]
    public function testImageRequired(BannerType $type, ?string $image, int $errors): void
    {
        self::assertCount($errors, (new ImageRequired())->validate($this->banner($type, image: $image)));
    }

    /**
     * A video banner needs a video URL a provider plays, or an uploaded file
     *
     * @param BannerType $type
     * @param string|null $url
     * @param string|null $path
     * @param int $errors
     * @return void
     */
    #[TestWith([BannerType::VIDEO, null, null, 1])]
    #[TestWith([BannerType::VIDEO, 'https://vimeo.com/1', null, 0])]
    #[TestWith([BannerType::VIDEO, 'https://example.com/clip', null, 1])]
    #[TestWith([BannerType::VIDEO, 'https://example.com/clip', 'banner_slider/video/a.mp4', 1])]
    #[TestWith([BannerType::VIDEO, null, 'banner_slider/video/a.mp4', 0])]
    #[TestWith([BannerType::IMAGE, null, null, 0])]
    #[TestWith([BannerType::IMAGE, 'https://example.com/clip', null, 0])]
    public function testVideoSourceRequired(BannerType $type, ?string $url, ?string $path, int $errors): void
    {
        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->method('resolve')->willReturnCallback(
            fn (string $source): ?ProviderInterface => str_starts_with($source, 'https://vimeo.com/')
                ? $this->createMock(ProviderInterface::class)
                : null
        );

        self::assertCount(
            $errors,
            (new VideoSourceRequired($resolver))->validate($this->banner($type, videoUrl: $url, videoPath: $path))
        );
    }

    /**
     * The message names the URL no provider plays
     *
     * @return void
     */
    public function testUnplayableUrlMessage(): void
    {
        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);

        $errors = (new VideoSourceRequired($resolver))->validate(
            $this->banner(BannerType::VIDEO, videoUrl: 'https://example.com/clip')
        );

        self::assertSame(
            [
                'No video provider can play "https://example.com/clip". '
                . 'Enter a supported video URL or upload a video file.',
            ],
            array_map(static fn (Phrase $error): string => $error->render(), $errors)
        );
    }

    /**
     * A custom banner needs content
     *
     * @param BannerType $type
     * @param string|null $content
     * @param int $errors
     * @return void
     */
    #[TestWith([BannerType::CUSTOM, null, 1])]
    #[TestWith([BannerType::CUSTOM, '  ', 1])]
    #[TestWith([BannerType::CUSTOM, '<p>Hi</p>', 0])]
    #[TestWith([BannerType::IMAGE, null, 0])]
    public function testContentRequired(BannerType $type, ?string $content, int $errors): void
    {
        self::assertCount($errors, (new ContentRequired())->validate($this->banner($type, content: $content)));
    }

    /**
     * A banner double
     *
     * @param BannerType $type
     * @param int|null $sliderId
     * @param string|null $image
     * @param string|null $videoUrl
     * @param string|null $videoPath
     * @param string|null $content
     * @return BannerInterface
     */
    private function banner(
        BannerType $type,
        ?int $sliderId = null,
        ?string $image = null,
        ?string $videoUrl = null,
        ?string $videoPath = null,
        ?string $content = null
    ): BannerInterface {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getType')->willReturn($type);
        $banner->method('getSliderId')->willReturn($sliderId);
        $banner->method('getImage')->willReturn($image);
        $banner->method('getVideoUrl')->willReturn($videoUrl);
        $banner->method('getVideoPath')->willReturn($videoPath);
        $banner->method('getContent')->willReturn($content);

        return $banner;
    }
}
