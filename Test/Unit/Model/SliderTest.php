<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model;

use DateTimeImmutable;
use Hryvinskyi\BannerSlider\Model\AbstractEntityModel;
use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use Hryvinskyi\BannerSlider\Model\Data\ResponsiveItemsCodec;
use Hryvinskyi\BannerSlider\Model\Slider;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ActiveWindow;
use Hryvinskyi\BannerSliderApi\Api\Value\ResponsiveItem;
use Hryvinskyi\BannerSliderApi\Api\Value\SlideEffect;
use Hryvinskyi\BannerSliderApi\Api\Value\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(Slider::class)]
#[CoversClass(AbstractEntityModel::class)]
class SliderTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var Slider
     */
    private Slider $slider;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();
        $this->slider = new Slider(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            new ResponsiveItemsCodec(),
            new RejectedStoredValueLog($this->logger),
            $this->modelResource(SliderInterface::SLIDER_ID)
        );
    }

    /**
     * A new slider reads the column defaults and is visible nowhere
     *
     * @return void
     */
    public function testNewSliderDefaults(): void
    {
        self::assertNull($this->slider->getSliderId());
        self::assertSame('', $this->slider->getName());
        self::assertTrue($this->slider->isEnabled());
        self::assertNull($this->slider->getLocation());
        self::assertSame(0, $this->slider->getPriority());
        self::assertSame(SlideEffect::SLIDE, $this->slider->getEffect());
        self::assertFalse($this->slider->isAutoWidthEnabled());
        self::assertFalse($this->slider->isAutoHeightEnabled());
        self::assertTrue($this->slider->isLoopEnabled());
        self::assertTrue($this->slider->isLazyLoadEnabled());
        self::assertTrue($this->slider->isAutoPlayEnabled());
        self::assertSame(SliderInterface::DEFAULT_AUTO_PLAY_INTERVAL, $this->slider->getAutoPlayInterval());
        self::assertTrue($this->slider->isAutoPlayToggleEnabled());
        self::assertTrue($this->slider->isNavigationEnabled());
        self::assertTrue($this->slider->isPaginationEnabled());
        self::assertSame([], $this->slider->getResponsiveItems());
        self::assertSame(0, $this->slider->getPreloadBannersCount());
        self::assertTrue($this->slider->getActiveWindow()->isAlways());
        self::assertTrue($this->slider->getVisibility()->isVisibleNowhere());
        self::assertNull($this->slider->getCustomCss());
        self::assertNull($this->slider->getCreatedAt());
    }

    /**
     * Stored effects map to a case, and anything unknown reads as slide
     *
     * @param string|null $stored
     * @param string $expected
     * @return void
     */
    #[TestWith([null, 'slide'])]
    #[TestWith(['', 'slide'])]
    #[TestWith(['cube', 'slide'])]
    #[TestWith(['fade', 'fade'])]
    public function testEffectReadsLegacyValues(?string $stored, string $expected): void
    {
        $this->slider->setData(SliderInterface::EFFECT, $stored);

        self::assertSame($expected, $this->slider->getEffect()->value);
    }

    /**
     * Stored flags and numbers arrive as strings from the database
     *
     * @return void
     */
    public function testStoredStringsAreTyped(): void
    {
        $this->slider->setData([
            SliderInterface::SLIDER_ID => '12',
            SliderInterface::NAME => 'Home',
            SliderInterface::STATUS => '0',
            SliderInterface::PRIORITY => '3',
            SliderInterface::LOOP => '0',
            SliderInterface::AUTO_PLAY_TIMEOUT => '7000',
            SliderInterface::PRELOAD_BANNERS_COUNT => '2',
            SliderInterface::LOCATION => 'home-top',
            SliderInterface::CREATED_AT => '2026-01-01 10:00:00',
        ]);

        self::assertSame(12, $this->slider->getSliderId());
        self::assertSame('Home', $this->slider->getName());
        self::assertFalse($this->slider->isEnabled());
        self::assertSame(3, $this->slider->getPriority());
        self::assertFalse($this->slider->isLoopEnabled());
        self::assertSame(7000, $this->slider->getAutoPlayInterval());
        self::assertSame(2, $this->slider->getPreloadBannersCount());
        self::assertSame('home-top', $this->slider->getLocation());
        self::assertSame('2026-01-01 10:00:00', $this->slider->getCreatedAt());
    }

    /**
     * The stored pause/play button flag: 0 hides it, and anything that is not a whole number reads as shown
     *
     * @param mixed $stored
     * @param bool $expected
     * @return void
     */
    #[TestWith(['0', false])]
    #[TestWith([0, false])]
    #[TestWith([false, false])]
    #[TestWith(['1', true])]
    #[TestWith([1, true])]
    #[TestWith([null, true])]
    #[TestWith(['', true])]
    #[TestWith(['yes', true])]
    #[TestWith([[1], true])]
    public function testStoredAutoPlayToggleFlag(mixed $stored, bool $expected): void
    {
        $this->slider->setData(SliderInterface::SHOW_AUTOPLAY_TOGGLE, $stored);

        self::assertSame($expected, $this->slider->isAutoPlayToggleEnabled());
    }

    /**
     * The pause/play button flag is stored as the 0/1 the column holds, independent of auto play
     *
     * @return void
     */
    public function testAutoPlayToggleIsIndependentOfAutoPlay(): void
    {
        $this->slider->setAutoPlayEnabled(true)->setAutoPlayToggleEnabled(false);

        self::assertSame(0, $this->slider->getData(SliderInterface::SHOW_AUTOPLAY_TOGGLE));
        self::assertTrue($this->slider->isAutoPlayEnabled());
        self::assertFalse($this->slider->isAutoPlayToggleEnabled());

        $this->slider->setAutoPlayEnabled(false)->setAutoPlayToggleEnabled(true);

        self::assertSame(1, $this->slider->getData(SliderInterface::SHOW_AUTOPLAY_TOGGLE));
        self::assertFalse($this->slider->isAutoPlayEnabled());
        self::assertTrue($this->slider->isAutoPlayToggleEnabled());
    }

    /**
     * A stored interval below the minimum reads as the minimum
     *
     * @return void
     */
    public function testShortStoredIntervalReadsAsMinimum(): void
    {
        $this->slider->setData(SliderInterface::AUTO_PLAY_TIMEOUT, '200');

        self::assertSame(SliderInterface::MIN_AUTO_PLAY_INTERVAL, $this->slider->getAutoPlayInterval());
    }

    /**
     * Unreadable or legacy-shaped responsive items read as none
     *
     * @param string $stored
     * @return void
     */
    #[TestWith(['{not json'])]
    #[TestWith(['{"768":{"items":3}}'])]
    #[TestWith(['"text"'])]
    public function testUnreadableResponsiveItemsReadAsNone(string $stored): void
    {
        $this->slider->setData(SliderInterface::RESPONSIVE_ITEMS, $stored);

        self::assertSame([], $this->slider->getResponsiveItems());
    }

    /**
     * Responsive items are stored in the list shape and read back ascending
     *
     * @return void
     */
    public function testResponsiveItemsRoundTrip(): void
    {
        $this->slider->setResponsiveItems([new ResponsiveItem(768, 3, '12px'), new ResponsiveItem(0, 1, null)]);

        self::assertSame(
            '[{"min_width":0,"per_page":1,"gap":null},{"min_width":768,"per_page":3,"gap":"12px"}]',
            $this->slider->getData(SliderInterface::RESPONSIVE_ITEMS)
        );
        $items = $this->slider->getResponsiveItems();
        self::assertCount(2, $items);
        self::assertSame(0, $items[0]->getMinWidth());
        self::assertSame('12px', $items[1]->getGap());
    }

    /**
     * An active window is stored in UTC and read back in UTC
     *
     * @return void
     */
    public function testActiveWindowIsStoredInUtc(): void
    {
        $this->slider->setActiveWindow(
            new ActiveWindow(new DateTimeImmutable('2026-01-01T12:00:00+02:00'), null)
        );

        self::assertSame('2026-01-01 10:00:00', $this->slider->getData(SliderInterface::FROM_DATE));
        self::assertNull($this->slider->getData(SliderInterface::TO_DATE));
        $window = $this->slider->getActiveWindow();
        self::assertSame('2026-01-01T10:00:00+00:00', $window->getFrom()?->format(DATE_ATOM));
        self::assertNull($window->getTo());
    }

    /**
     * A stored window that ends before it starts reads as its start instead of failing
     *
     * @return void
     */
    public function testInvertedStoredWindowReadsAsItsStart(): void
    {
        $this->slider->setData(SliderInterface::FROM_DATE, '2026-05-01 00:00:00');
        $this->slider->setData(SliderInterface::TO_DATE, '2026-04-01 00:00:00');

        $window = $this->slider->getActiveWindow();

        self::assertSame('2026-05-01 00:00:00', $window->getFrom()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-01 00:00:00', $window->getTo()?->format('Y-m-d H:i:s'));
    }

    /**
     * An unreadable stored date reads as an open end
     *
     * @return void
     */
    public function testUnreadableStoredDateReadsAsOpen(): void
    {
        $this->slider->setData(SliderInterface::TO_DATE, 'not a date');

        self::assertNull($this->slider->getActiveWindow()->getTo());
    }

    /**
     * Visibility is read from the lists the resource model attaches and the stored flag
     *
     * @return void
     */
    public function testVisibilityFromStoredLists(): void
    {
        $this->slider->setData(SliderInterface::STORE_IDS, ['1', 0]);
        $this->slider->setData(SliderInterface::CUSTOMER_GROUP_IDS, [2]);
        $this->slider->setData(SliderInterface::ALL_CUSTOMER_GROUPS, '0');

        $visibility = $this->slider->getVisibility();

        self::assertSame([0, 1], $visibility->getStoreIds());
        self::assertSame([2], $visibility->getCustomerGroupIds());
        self::assertFalse($visibility->isForAllCustomerGroups());
    }

    /**
     * Group rows left over while the all-groups flag is on do not make the read fail
     *
     * @return void
     */
    public function testAllGroupsFlagIgnoresLeftoverGroupRows(): void
    {
        $this->slider->setData(SliderInterface::STORE_IDS, [0]);
        $this->slider->setData(SliderInterface::CUSTOMER_GROUP_IDS, [3]);
        $this->slider->setData(SliderInterface::ALL_CUSTOMER_GROUPS, '1');

        $visibility = $this->slider->getVisibility();

        self::assertTrue($visibility->isForAllCustomerGroups());
        self::assertSame([], $visibility->getCustomerGroupIds());
    }

    /**
     * Setting a visibility fills the lists and the flag
     *
     * @return void
     */
    public function testSetVisibility(): void
    {
        $visibility = new Visibility([2, 1], [], true);

        $this->slider->setVisibility($visibility);

        self::assertSame([1, 2], $this->slider->getData(SliderInterface::STORE_IDS));
        self::assertSame(1, $this->slider->getData(SliderInterface::ALL_CUSTOMER_GROUPS));
        self::assertTrue($this->slider->getVisibility()->equals($visibility));
    }

    /**
     * Valid values pass the setters
     *
     * @return void
     */
    public function testSettersStoreValidValues(): void
    {
        $this->slider->setSliderId(4)
            ->setName('Home')
            ->setIsEnabled(false)
            ->setLocation('home-top')
            ->setPriority(0)
            ->setEffect(SlideEffect::FADE)
            ->setAutoPlayInterval(SliderInterface::MIN_AUTO_PLAY_INTERVAL)
            ->setPreloadBannersCount(0)
            ->setCustomCss('.banner-slider-4 { color: red; }')
            ->setAutoWidthEnabled(true)
            ->setAutoHeightEnabled(true)
            ->setLoopEnabled(false)
            ->setLazyLoadEnabled(false)
            ->setAutoPlayEnabled(false)
            ->setAutoPlayToggleEnabled(false)
            ->setNavigationEnabled(false)
            ->setPaginationEnabled(false);

        self::assertSame(4, $this->slider->getSliderId());
        self::assertFalse($this->slider->isEnabled());
        self::assertSame(SlideEffect::FADE, $this->slider->getEffect());
        self::assertSame(SliderInterface::MIN_AUTO_PLAY_INTERVAL, $this->slider->getAutoPlayInterval());
        self::assertSame('.banner-slider-4 { color: red; }', $this->slider->getCustomCss());
        self::assertTrue($this->slider->isAutoWidthEnabled());
        self::assertTrue($this->slider->isAutoHeightEnabled());
        self::assertFalse($this->slider->isLoopEnabled());
        self::assertFalse($this->slider->isLazyLoadEnabled());
        self::assertFalse($this->slider->isAutoPlayEnabled());
        self::assertFalse($this->slider->isAutoPlayToggleEnabled());
        self::assertFalse($this->slider->isNavigationEnabled());
        self::assertFalse($this->slider->isPaginationEnabled());

        $this->slider->setLocation(null)->setCustomCss(null);
        self::assertNull($this->slider->getLocation());
        self::assertNull($this->slider->getCustomCss());
    }

    /**
     * Each setter rejects a value that breaks its field's rule
     *
     * @param string $setter
     * @param int|string $value
     * @return void
     */
    #[TestWith(['setSliderId', 0])]
    #[TestWith(['setName', '  '])]
    #[TestWith(['setLocation', 'home top'])]
    #[TestWith(['setPriority', -1])]
    #[TestWith(['setAutoPlayInterval', 999])]
    #[TestWith(['setPreloadBannersCount', -1])]
    #[TestWith(['setCustomCss', '.a{}</style><script>'])]
    public function testSetterGuards(string $setter, int|string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $callable = [$this->slider, $setter];
        self::assertIsCallable($callable);
        $callable($value);
    }

    /**
     * Identities hold the generic tag, the slider tag and the location tag
     *
     * @return void
     */
    public function testIdentities(): void
    {
        $this->slider->setSliderId(5)->setLocation('Home-Top');

        self::assertSame(
            [
                SliderInterface::CACHE_TAG,
                SliderInterface::CACHE_TAG . '_5',
                SliderInterface::LOCATION_CACHE_TAG . '_home_top',
            ],
            $this->slider->getIdentities()
        );
    }

    /**
     * A location change tags both the new and the previous location
     *
     * @return void
     */
    public function testIdentitiesIncludePreviousLocation(): void
    {
        $this->slider->setOrigData(SliderInterface::LOCATION, 'sidebar');
        $this->slider->setSliderId(5)->setLocation('home');

        self::assertSame(
            [
                SliderInterface::CACHE_TAG,
                SliderInterface::CACHE_TAG . '_5',
                SliderInterface::LOCATION_CACHE_TAG . '_home',
                SliderInterface::LOCATION_CACHE_TAG . '_sidebar',
            ],
            $this->slider->getIdentities()
        );
    }

    /**
     * A stored location that is not a valid code adds no location tag
     *
     * @return void
     */
    public function testIdentitiesSkipInvalidStoredLocation(): void
    {
        $this->slider->setData(SliderInterface::LOCATION, 'not valid!');

        self::assertSame([SliderInterface::CACHE_TAG], $this->slider->getIdentities());
    }

    /**
     * Stored custom CSS the setter would reject reads as none and is logged once for the slider
     *
     * @return void
     */
    public function testRejectedStoredCustomCssReadsAsNone(): void
    {
        $this->slider->setData(SliderInterface::SLIDER_ID, 4);
        $this->slider->setData(SliderInterface::CUSTOM_CSS, '.a{}</style><script>alert(1)</script>');
        $this->logger->expects(self::once())->method('debug')->with(
            'Banner slider: the stored custom_css of slider 4 is read as empty: '
            . 'Slider custom CSS must not contain "<".'
        );

        self::assertNull($this->slider->getCustomCss());
        self::assertNull($this->slider->getCustomCss());
    }
}
