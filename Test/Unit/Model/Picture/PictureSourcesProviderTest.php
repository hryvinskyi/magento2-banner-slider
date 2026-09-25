<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Picture;

use Hryvinskyi\BannerSlider\Model\Breakpoint;
use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSlider\Model\Image\ImageConverter;
use Hryvinskyi\BannerSlider\Model\Image\ImageFormatRegistry;
use Hryvinskyi\BannerSlider\Model\Picture\PictureSourcesProvider;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\Collection as BreakpointCollection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\CollectionFactory as BreakpointCollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\Collection as CropCollection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory as CropCollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFile;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PictureSourcesProvider::class)]
class PictureSourcesProviderTest extends TestCase
{
    use EntityModelArguments;

    /**
     * Crops the crop query returns
     *
     * @var list<ResponsiveCrop>
     */
    private array $crops = [];

    /**
     * Breakpoints the breakpoint query returns, in rendering order
     *
     * @var list<Breakpoint>
     */
    private array $breakpoints = [];

    /**
     * Slider ids the breakpoint query was asked for
     *
     * @var list<int>|null
     */
    private ?array $requestedSliderIds = null;

    /**
     * @var CropCollectionFactory&MockObject
     */
    private MockObject $cropCollectionFactory;

    /**
     * @var BreakpointCollectionFactory&MockObject
     */
    private MockObject $breakpointCollectionFactory;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var PictureSourcesProvider
     */
    private PictureSourcesProvider $provider;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $cropCollection = $this->createMock(CropCollection::class);
        $cropCollection->expects(self::any())->method('addBannerIdsFilter')->willReturnSelf();
        $cropCollection->expects(self::any())->method('addEnabledFilter')->willReturnSelf();
        $cropCollection->expects(self::any())->method('addGeneratedFilter')->willReturnSelf();
        $cropCollection->expects(self::any())->method('joinBannerSliderId')->willReturnSelf();
        $cropCollection->method('getItems')->willReturnCallback(fn (): array => $this->crops);
        $this->cropCollectionFactory = $this->createMock(CropCollectionFactory::class);
        $this->cropCollectionFactory->method('create')->willReturn($cropCollection);

        $breakpointCollection = $this->createMock(BreakpointCollection::class);
        $breakpointCollection->method('addSliderIdsFilter')->willReturnCallback(
            function (array $sliderIds) use ($breakpointCollection): BreakpointCollection {
                $this->requestedSliderIds = array_values(array_filter($sliderIds, 'is_int'));

                return $breakpointCollection;
            }
        );
        $breakpointCollection->expects(self::any())->method('addEnabledFilter')->willReturnSelf();
        $breakpointCollection->expects(self::any())->method('orderForRendering')->willReturnSelf();
        $breakpointCollection->method('getItems')->willReturnCallback(fn (): array => $this->breakpoints);
        $this->breakpointCollectionFactory = $this->createMock(BreakpointCollectionFactory::class);
        $this->breakpointCollectionFactory->method('create')->willReturn($breakpointCollection);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->provider = new PictureSourcesProvider(
            $this->cropCollectionFactory,
            $this->breakpointCollectionFactory,
            new ImageFormatRegistry($this->createMock(ImageConverter::class), [
                'jpeg' => ['mime' => 'image/jpeg', 'extension' => 'jpg', 'aliases' => ['jpeg' => 'jpeg']],
                'png' => ['mime' => 'image/png', 'extension' => 'png'],
                'webp' => ['mime' => 'image/webp', 'extension' => 'webp', 'variant' => true, 'preference' => 10],
                'avif' => ['mime' => 'image/avif', 'extension' => 'avif', 'variant' => true, 'preference' => 20],
            ]),
            new CropTargetSize(),
            $this->logger
        );
    }

    /**
     * Sources follow breakpoint order; foreign, disabled and unrenderable crops are left out; every id is a key
     *
     * @return void
     */
    public function testAssemblesSources(): void
    {
        $this->breakpoints = [
            $this->breakpoint(10, 1, 'desktop', 1200, 1920, 600, 10),
            $this->breakpoint(11, 1, 'tablet', 768, 992, null, 30),
            $this->breakpoint(12, 1, 'mobile', 0, 767, 500, 40),
            $this->breakpoint(20, 2, 'all', 0, 800, 400, 0),
            $this->breakpoint(30, 2, 'broken', 0, 0, null, 0),
        ];
        $this->crops = [
            $this->crop(1, 5, 12, 1, new CropRect(0, 0, 1000, 500), 'banner_slider/responsive/5/mobile_a.JPG', [
                new CropVariant('webp', 85, 'banner_slider/responsive/5/mobile_a.webp'),
                new CropVariant('png', 90, 'banner_slider/responsive/5/mobile_a.png'),
                new CropVariant('avif', 80, 'banner_slider/responsive/5/mobile_a.avif'),
            ]),
            $this->crop(2, 5, 10, 1, null, 'legacy_slider/image/legacy.jpeg'),
            $this->crop(3, 5, 11, 1, new CropRect(0, 0, 1600, 900), 'banner_slider/responsive/5/tablet_b.png', [
                new CropVariant('webp', 85, null),
                new CropVariant('png', 85, 'banner_slider/responsive/5/tablet_b_copy.png'),
            ]),
            $this->crop(4, 6, 11, 1, null, 'banner_slider/responsive/6/tablet_c.jpg'),
            $this->crop(5, 6, 10, 1, new CropRect(0, 0, 10, 10), 'banner_slider/responsive/6/desktop_d.bmp'),
            $this->crop(6, 7, 10, 2, new CropRect(0, 0, 10, 10), 'banner_slider/responsive/7/desktop_e.jpg'),
            $this->crop(7, 7, 20, 2, new CropRect(0, 0, 10, 10), 'banner_slider/responsive/7/all_f.jpg'),
            $this->crop(8, 7, 99, 2, new CropRect(0, 0, 10, 10), 'banner_slider/responsive/7/off_g.jpg'),
            $this->crop(9, 7, 30, 2, new CropRect(0, 0, 10, 10), 'banner_slider/responsive/7/broken_h.jpg'),
        ];
        $this->logger->expects(self::exactly(3))->method('warning');

        $result = $this->provider->getForBanners([5, 6, 7, 8]);

        self::assertSame([5, 6, 7, 8], array_keys($result));
        self::assertSame([1, 2], $this->requestedSliderIds);
        self::assertSame(
            [
                ['desktop', 1920, 600, ['legacy_slider/image/legacy.jpeg:jpeg']],
                ['tablet', 992, 558, ['banner_slider/responsive/5/tablet_b.png:png']],
                ['mobile', 767, 500, [
                    'banner_slider/responsive/5/mobile_a.avif:avif',
                    'banner_slider/responsive/5/mobile_a.webp:webp',
                    'banner_slider/responsive/5/mobile_a.JPG:jpeg',
                ]],
            ],
            $this->describe($result[5])
        );
        self::assertSame([], $result[6]);
        self::assertSame(
            [['all', 800, 400, ['banner_slider/responsive/7/all_f.jpg:jpeg']]],
            $this->describe($result[7])
        );
        self::assertSame([], $result[8]);
    }

    /**
     * No valid banner id, no query; ids stay keys
     *
     * @return void
     */
    public function testNoValidIdsQueriesNothing(): void
    {
        $this->cropCollectionFactory->expects(self::never())->method('create');

        self::assertSame([0 => [], -3 => []], $this->provider->getForBanners([0, -3]));
        self::assertSame([], $this->provider->getForBanners([]));
    }

    /**
     * Without any crop the breakpoints are not read
     *
     * @return void
     */
    public function testNoCropsReadsNoBreakpoints(): void
    {
        $this->breakpointCollectionFactory->expects(self::never())->method('create');

        self::assertSame([4 => []], $this->provider->getForBanners([4]));
    }

    /**
     * A readable form of picture sources
     *
     * @param list<PictureSource> $sources
     * @return list<array{0: string, 1: int, 2: int, 3: list<string>}>
     */
    private function describe(array $sources): array
    {
        return array_map(
            fn (PictureSource $source): array => [
                $source->getBreakpoint()->getIdentifier(),
                $source->getDimensions()->getWidth(),
                $source->getDimensions()->getHeight(),
                array_map(
                    fn (ImageFile $image): string => $image->getRelativePath() . ':' . $image->getFormat()->getCode(),
                    $source->getImages()
                ),
            ],
            $sources
        );
    }

    /**
     * A stored breakpoint
     *
     * @param int $id
     * @param int $sliderId
     * @param string $identifier
     * @param int $minWidth
     * @param int $width Stored as is, so 0 stands for a broken row
     * @param int|null $height
     * @param int $sortOrder
     * @return Breakpoint
     */
    private function breakpoint(
        int $id,
        int $sliderId,
        string $identifier,
        int $minWidth,
        int $width,
        ?int $height,
        int $sortOrder
    ): Breakpoint {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();
        $breakpoint = new Breakpoint(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(BreakpointInterface::BREAKPOINT_ID)
        );
        $breakpoint->setBreakpointId($id)
            ->setSliderId($sliderId)
            ->setIdentifier($identifier)
            ->setMediaQuery('(min-width: ' . $minWidth . 'px)')
            ->setMinWidth($minWidth)
            ->setTargetHeight($height)
            ->setSortOrder($sortOrder);
        $breakpoint->setData(BreakpointInterface::TARGET_WIDTH, $width);

        return $breakpoint;
    }

    /**
     * A stored crop as the crop query loads it, with its banner's slider id
     *
     * @param int $id
     * @param int $bannerId
     * @param int $breakpointId
     * @param int $bannerSliderId
     * @param CropRect|null $rect
     * @param string $croppedImage
     * @param list<CropVariant> $variants
     * @return ResponsiveCrop
     */
    private function crop(
        int $id,
        int $bannerId,
        int $breakpointId,
        int $bannerSliderId,
        ?CropRect $rect,
        string $croppedImage,
        array $variants = []
    ): ResponsiveCrop {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();
        $crop = new ResponsiveCrop(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(ResponsiveCropInterface::CROP_ID)
        );
        $crop->setCropId($id)
            ->setBannerId($bannerId)
            ->setBreakpointId($breakpointId)
            ->setCropRect($rect)
            ->setCroppedImage($croppedImage)
            ->setVariants($variants);
        $crop->setData(CropCollection::BANNER_SLIDER_ID, (string)$bannerSliderId);

        return $crop;
    }
}
