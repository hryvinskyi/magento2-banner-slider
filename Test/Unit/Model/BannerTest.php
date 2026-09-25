<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model;

use DateTimeImmutable;
use Hryvinskyi\BannerSlider\Model\Banner;
use Hryvinskyi\BannerSlider\Model\Banner\BannerUrlPolicy;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ActiveWindow;
use Hryvinskyi\BannerSliderApi\Api\Value\AspectRatio;
use Hryvinskyi\BannerSliderApi\Api\Value\AspectRatioParser;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(Banner::class)]
class BannerTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var Banner
     */
    private Banner $banner;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();
        $this->banner = new Banner(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            new AspectRatioParser(),
            new BannerUrlPolicy(),
            new RejectedStoredValueLog($this->logger),
            $this->modelResource(BannerInterface::BANNER_ID)
        );
    }

    /**
     * A new banner reads the column defaults
     *
     * @return void
     */
    public function testNewBannerDefaults(): void
    {
        self::assertNull($this->banner->getBannerId());
        self::assertNull($this->banner->getSliderId());
        self::assertSame('', $this->banner->getName());
        self::assertTrue($this->banner->isEnabled());
        self::assertSame(BannerType::IMAGE, $this->banner->getType());
        self::assertNull($this->banner->getImage());
        self::assertNull($this->banner->getImageDimensions());
        self::assertTrue((new AspectRatio(16, 9))->equals($this->banner->getVideoAspectRatio()));
        self::assertFalse($this->banner->isVideoAsBackground());
        self::assertTrue($this->banner->isOpenInNewTab());
        self::assertSame(0, $this->banner->getPosition());
        self::assertFalse($this->banner->isPreloadEnabled());
        self::assertTrue($this->banner->getActiveWindow()->isAlways());
    }

    /**
     * Stored types map to a case, and an unknown type reads as custom content
     *
     * @param int|string $stored
     * @param BannerType $expected
     * @return void
     */
    #[TestWith(['0', BannerType::IMAGE])]
    #[TestWith(['1', BannerType::VIDEO])]
    #[TestWith([2, BannerType::CUSTOM])]
    #[TestWith(['7', BannerType::CUSTOM])]
    #[TestWith(['video', BannerType::CUSTOM])]
    public function testTypeReadsLegacyValues(int|string $stored, BannerType $expected): void
    {
        $this->banner->setData(BannerInterface::TYPE, $stored);

        self::assertSame($expected, $this->banner->getType());
    }

    /**
     * An unreadable stored aspect ratio reads as 16:9
     *
     * @param string $stored
     * @param string $expected
     * @return void
     */
    #[TestWith(['4:3', '4:3'])]
    #[TestWith([' 21 : 9 ', '21:9'])]
    #[TestWith(['wide', '16:9'])]
    #[TestWith(['0:9', '16:9'])]
    #[TestWith(['', '16:9'])]
    public function testAspectRatioReadsLegacyValues(string $stored, string $expected): void
    {
        $this->banner->setData(BannerInterface::VIDEO_ASPECT_RATIO, $stored);

        self::assertSame($expected, $this->banner->getVideoAspectRatio()->toString());
    }

    /**
     * Image dimensions need both sides
     *
     * @return void
     */
    public function testImageDimensions(): void
    {
        $this->banner->setData(BannerInterface::IMAGE_WIDTH, '1920');
        self::assertNull($this->banner->getImageDimensions());

        $this->banner->setData(BannerInterface::IMAGE_HEIGHT, '600');
        $dimensions = $this->banner->getImageDimensions();
        self::assertNotNull($dimensions);
        self::assertTrue((new Dimensions(1920, 600))->equals($dimensions));

        $this->banner->setImageDimensions(null);
        self::assertNull($this->banner->getImageDimensions());
    }

    /**
     * Valid values pass the setters and read back
     *
     * @return void
     */
    public function testSettersStoreValidValues(): void
    {
        $window = new ActiveWindow(new DateTimeImmutable('2026-01-01 00:00:00'), null);
        $this->banner->setBannerId(3)
            ->setSliderId(2)
            ->setName('Summer')
            ->setIsEnabled(false)
            ->setType(BannerType::VIDEO)
            ->setContent('<p>Hi</p>')
            ->setImage('banner_slider/image/a.jpg')
            ->setImageDimensions(new Dimensions(800, 600))
            ->setVideoUrl('https://www.youtube.com/watch?v=abc')
            ->setVideoPath('banner_slider/video/a.mp4')
            ->setVideoAspectRatio(new AspectRatio(4, 3))
            ->setVideoAsBackground(true)
            ->setLinkUrl('/sale')
            ->setTitle('Sale')
            ->setOpenInNewTab(false)
            ->setActiveWindow($window)
            ->setPosition(4)
            ->setPreloadEnabled(true);

        self::assertSame(3, $this->banner->getBannerId());
        self::assertSame(2, $this->banner->getSliderId());
        self::assertSame('Summer', $this->banner->getName());
        self::assertFalse($this->banner->isEnabled());
        self::assertSame(BannerType::VIDEO, $this->banner->getType());
        self::assertSame('<p>Hi</p>', $this->banner->getContent());
        self::assertSame('banner_slider/image/a.jpg', $this->banner->getImage());
        self::assertSame('https://www.youtube.com/watch?v=abc', $this->banner->getVideoUrl());
        self::assertSame('banner_slider/video/a.mp4', $this->banner->getVideoPath());
        self::assertSame('4:3', $this->banner->getVideoAspectRatio()->toString());
        self::assertTrue($this->banner->isVideoAsBackground());
        self::assertSame('/sale', $this->banner->getLinkUrl());
        self::assertSame('Sale', $this->banner->getTitle());
        self::assertFalse($this->banner->isOpenInNewTab());
        self::assertTrue($window->equals($this->banner->getActiveWindow()));
        self::assertSame(4, $this->banner->getPosition());
        self::assertTrue($this->banner->isPreloadEnabled());

        $this->banner->setImage(null)->setVideoUrl(null)->setVideoPath(null)->setLinkUrl(null);
        self::assertNull($this->banner->getImage());
        self::assertNull($this->banner->getVideoUrl());
        self::assertNull($this->banner->getVideoPath());
        self::assertNull($this->banner->getLinkUrl());
    }

    /**
     * Each setter rejects a value that breaks its field's rule
     *
     * @param string $setter
     * @param int|string $value
     * @return void
     */
    #[TestWith(['setBannerId', 0])]
    #[TestWith(['setSliderId', -2])]
    #[TestWith(['setName', ''])]
    #[TestWith(['setImage', '/var/www/pub/media/a.jpg'])]
    #[TestWith(['setImage', ''])]
    #[TestWith(['setVideoPath', 'https://cdn.example.com/a.mp4'])]
    #[TestWith(['setVideoUrl', 'ftp://example.com/a.mp4'])]
    #[TestWith(['setVideoUrl', 'banner_slider/video/a.mp4'])]
    #[TestWith(['setLinkUrl', 'javascript:alert(1)'])]
    #[TestWith(['setLinkUrl', 'data:text/html,x'])]
    #[TestWith(['setPosition', -1])]
    public function testSetterGuards(string $setter, int|string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $callable = [$this->banner, $setter];
        self::assertIsCallable($callable);
        $callable($value);
    }

    /**
     * A stored window that ends before it starts reads as its start
     *
     * @return void
     */
    public function testInvertedStoredWindowReadsAsItsStart(): void
    {
        $this->banner->setData(BannerInterface::FROM_DATE, '2026-05-01 00:00:00');
        $this->banner->setData(BannerInterface::TO_DATE, '2026-04-01 00:00:00');

        $window = $this->banner->getActiveWindow();

        self::assertEquals($window->getFrom(), $window->getTo());
    }

    /**
     * Identities hold the banner tag and its slider's tag
     *
     * @return void
     */
    public function testIdentities(): void
    {
        $this->banner->setBannerId(3)->setSliderId(2);

        self::assertSame(
            [BannerInterface::CACHE_TAG . '_3', SliderInterface::CACHE_TAG . '_2'],
            $this->banner->getIdentities()
        );
    }

    /**
     * A banner moved to another slider tags the previous slider too
     *
     * @return void
     */
    public function testIdentitiesIncludePreviousSlider(): void
    {
        $this->banner->setOrigData(BannerInterface::SLIDER_ID, '1');
        $this->banner->setBannerId(3)->setSliderId(2);

        self::assertSame(
            [
                BannerInterface::CACHE_TAG . '_3',
                SliderInterface::CACHE_TAG . '_2',
                SliderInterface::CACHE_TAG . '_1',
            ],
            $this->banner->getIdentities()
        );
    }

    /**
     * A stored link the URL policy rejects reads as none and is logged once for the banner
     *
     * @param string $stored
     * @return void
     */
    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(["java\tscript:alert(1)"])]
    #[TestWith(['data:text/html,x'])]
    public function testRejectedStoredLinkReadsAsNone(string $stored): void
    {
        $this->banner->setData(BannerInterface::BANNER_ID, 9);
        $this->banner->setData(BannerInterface::LINK_URL, $stored);
        $this->logger->expects(self::once())->method('debug')
            ->with(self::stringStartsWith('Banner slider: the stored link_url of banner 9 is read as empty: '));

        self::assertNull($this->banner->getLinkUrl());
        self::assertNull($this->banner->getLinkUrl());
    }

    /**
     * Surrounding whitespace of a stored link is dropped, as browsers drop it
     *
     * @return void
     */
    public function testStoredLinkIsTrimmed(): void
    {
        $this->banner->setData(BannerInterface::LINK_URL, " https://shop.test/sale\n");
        $this->logger->expects(self::never())->method('debug');

        self::assertSame('https://shop.test/sale', $this->banner->getLinkUrl());
    }
}
