<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use Hryvinskyi\BannerSlider\Model\Data\ResponsiveItemsCodec;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderExtensionInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ActiveWindow;
use Hryvinskyi\BannerSliderApi\Api\Value\LocationCode;
use Hryvinskyi\BannerSliderApi\Api\Value\SlideEffect;
use Hryvinskyi\BannerSliderApi\Api\Value\Visibility;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Stored slider: typed accessors over the `hryvinskyi_banner_slider` row and its store and customer group links.
 *
 * Getters never fail on stored data: an unknown effect reads as slide, an auto play interval below the minimum reads
 * as the minimum, unreadable responsive items read as none, an inverted window reads as its start, and custom CSS the
 * setter would reject (it contains "<") reads as none and is logged (see RejectedStoredValueLog). A new slider
 * is visible nowhere until its visibility is set. The link rows behind the visibility are read and written by the
 * slider resource model.
 */
class Slider extends AbstractEntityModel implements SliderInterface, IdentityInterface
{
    /**
     * @var string
     */
    protected $_cacheTag = SliderInterface::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider';

    /**
     * @var string
     */
    protected $_eventObject = 'slider';

    /**
     * The rule custom CSS breaks when it contains "<"
     */
    private const CUSTOM_CSS_RULE = 'Slider custom CSS must not contain "<".';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param ResponsiveItemsCodec $responsiveItemsCodec
     * @param RejectedStoredValueLog $rejectedValueLog
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        private readonly ResponsiveItemsCodec $responsiveItemsCodec,
        private readonly RejectedStoredValueLog $rejectedValueLog,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Slider::class);
    }

    /**
     * Cache tags of every page showing this slider: the generic tag, its own tag and its location tags
     *
     * When the location changes, the tag of the previous location is included too, so pages that showed the slider
     * there are cleaned as well.
     *
     * @return list<string>
     */
    public function getIdentities(): array
    {
        $tags = [SliderInterface::CACHE_TAG];
        $sliderId = $this->getSliderId();
        if ($sliderId !== null) {
            $tags[] = SliderInterface::CACHE_TAG . '_' . $sliderId;
        }

        $originalLocation = $this->getOrigData(self::LOCATION);
        $locations = [$this->getLocation(), is_string($originalLocation) ? $originalLocation : null];
        foreach ($locations as $location) {
            $tag = $this->locationTag($location);
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @inheritDoc
     */
    public function getSliderId(): ?int
    {
        return $this->readId(self::SLIDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setSliderId(int $sliderId): SliderInterface
    {
        $this->assertPositiveId('Slider id', $sliderId);

        return $this->setData(self::SLIDER_ID, $sliderId);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->readString(self::NAME) ?? '';
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name): SliderInterface
    {
        $this->assertNotBlank('Slider name', $name);

        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return $this->readBool(self::STATUS, true);
    }

    /**
     * @inheritDoc
     */
    public function setIsEnabled(bool $enabled): SliderInterface
    {
        return $this->setData(self::STATUS, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function getLocation(): ?string
    {
        return $this->readNonBlankString(self::LOCATION);
    }

    /**
     * @inheritDoc
     */
    public function setLocation(?string $location): SliderInterface
    {
        if ($location === null) {
            return $this->setData(self::LOCATION, null);
        }

        return $this->setData(self::LOCATION, (new LocationCode($location))->getCode());
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return max(0, $this->readInt(self::PRIORITY) ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function setPriority(int $priority): SliderInterface
    {
        $this->assertNotNegative('Slider priority', $priority);

        return $this->setData(self::PRIORITY, $priority);
    }

    /**
     * @inheritDoc
     */
    public function getEffect(): SlideEffect
    {
        return SlideEffect::tryFrom($this->readString(self::EFFECT) ?? '') ?? SlideEffect::SLIDE;
    }

    /**
     * @inheritDoc
     */
    public function setEffect(SlideEffect $effect): SliderInterface
    {
        return $this->setData(self::EFFECT, $effect->value);
    }

    /**
     * @inheritDoc
     */
    public function isAutoWidthEnabled(): bool
    {
        return $this->readBool(self::AUTO_WIDTH, false);
    }

    /**
     * @inheritDoc
     */
    public function setAutoWidthEnabled(bool $enabled): SliderInterface
    {
        return $this->setData(self::AUTO_WIDTH, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function isAutoHeightEnabled(): bool
    {
        return $this->readBool(self::AUTO_HEIGHT, false);
    }

    /**
     * @inheritDoc
     */
    public function setAutoHeightEnabled(bool $enabled): SliderInterface
    {
        return $this->setData(self::AUTO_HEIGHT, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function isLoopEnabled(): bool
    {
        return $this->readBool(self::LOOP, true);
    }

    /**
     * @inheritDoc
     */
    public function setLoopEnabled(bool $enabled): SliderInterface
    {
        return $this->setData(self::LOOP, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function isLazyLoadEnabled(): bool
    {
        return $this->readBool(self::LAZY_LOAD, true);
    }

    /**
     * @inheritDoc
     */
    public function setLazyLoadEnabled(bool $enabled): SliderInterface
    {
        return $this->setData(self::LAZY_LOAD, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function isAutoPlayEnabled(): bool
    {
        return $this->readBool(self::AUTO_PLAY, true);
    }

    /**
     * @inheritDoc
     */
    public function setAutoPlayEnabled(bool $enabled): SliderInterface
    {
        return $this->setData(self::AUTO_PLAY, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function getAutoPlayInterval(): int
    {
        $interval = $this->readInt(self::AUTO_PLAY_TIMEOUT) ?? self::DEFAULT_AUTO_PLAY_INTERVAL;

        return max(self::MIN_AUTO_PLAY_INTERVAL, $interval);
    }

    /**
     * @inheritDoc
     */
    public function setAutoPlayInterval(int $milliseconds): SliderInterface
    {
        if ($milliseconds < self::MIN_AUTO_PLAY_INTERVAL) {
            throw new \InvalidArgumentException(sprintf(
                'Slider auto play interval must be at least %d ms, got %d.',
                self::MIN_AUTO_PLAY_INTERVAL,
                $milliseconds
            ));
        }

        return $this->setData(self::AUTO_PLAY_TIMEOUT, $milliseconds);
    }

    /**
     * @inheritDoc
     */
    public function isAutoPlayToggleEnabled(): bool
    {
        return $this->readBool(self::SHOW_AUTOPLAY_TOGGLE, true);
    }

    /**
     * @inheritDoc
     */
    public function setAutoPlayToggleEnabled(bool $enabled): SliderInterface
    {
        return $this->setData(self::SHOW_AUTOPLAY_TOGGLE, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function isNavigationEnabled(): bool
    {
        return $this->readBool(self::NAV, true);
    }

    /**
     * @inheritDoc
     */
    public function setNavigationEnabled(bool $enabled): SliderInterface
    {
        return $this->setData(self::NAV, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function isPaginationEnabled(): bool
    {
        return $this->readBool(self::DOTS, true);
    }

    /**
     * @inheritDoc
     */
    public function setPaginationEnabled(bool $enabled): SliderInterface
    {
        return $this->setData(self::DOTS, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function getResponsiveItems(): array
    {
        return $this->responsiveItemsCodec->decode($this->readString(self::RESPONSIVE_ITEMS));
    }

    /**
     * @inheritDoc
     */
    public function setResponsiveItems(array $items): SliderInterface
    {
        return $this->setData(self::RESPONSIVE_ITEMS, $this->responsiveItemsCodec->encode($items));
    }

    /**
     * @inheritDoc
     */
    public function getPreloadBannersCount(): int
    {
        return max(0, $this->readInt(self::PRELOAD_BANNERS_COUNT) ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function setPreloadBannersCount(int $count): SliderInterface
    {
        $this->assertNotNegative('Slider preload banners count', $count);

        return $this->setData(self::PRELOAD_BANNERS_COUNT, $count);
    }

    /**
     * @inheritDoc
     */
    public function getActiveWindow(): ActiveWindow
    {
        return $this->readActiveWindow(self::FROM_DATE, self::TO_DATE);
    }

    /**
     * @inheritDoc
     */
    public function setActiveWindow(ActiveWindow $window): SliderInterface
    {
        $this->storeActiveWindow($window, self::FROM_DATE, self::TO_DATE);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getVisibility(): Visibility
    {
        $storeIds = $this->readIdList(self::STORE_IDS);
        $allCustomerGroups = $this->readBool(self::ALL_CUSTOMER_GROUPS, false);
        if ($allCustomerGroups) {
            return new Visibility($storeIds, [], true);
        }

        return new Visibility($storeIds, $this->readIdList(self::CUSTOMER_GROUP_IDS), false);
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(Visibility $visibility): SliderInterface
    {
        $this->setData(self::STORE_IDS, $visibility->getStoreIds());
        $this->setData(self::CUSTOMER_GROUP_IDS, $visibility->getCustomerGroupIds());

        return $this->setData(self::ALL_CUSTOMER_GROUPS, (int)$visibility->isForAllCustomerGroups());
    }

    /**
     * @inheritDoc
     */
    public function getCustomCss(): ?string
    {
        $customCss = $this->readNonBlankString(self::CUSTOM_CSS);
        if ($customCss === null || $this->isAllowedCustomCss($customCss)) {
            return $customCss;
        }
        $this->rejectedValueLog->report('slider', $this->getSliderId(), self::CUSTOM_CSS, self::CUSTOM_CSS_RULE);

        return null;
    }

    /**
     * @inheritDoc
     */
    public function setCustomCss(?string $customCss): SliderInterface
    {
        if ($customCss !== null && !$this->isAllowedCustomCss($customCss)) {
            throw new \InvalidArgumentException(self::CUSTOM_CSS_RULE);
        }

        return $this->setData(self::CUSTOM_CSS, $customCss);
    }

    /**
     * Whether custom CSS may be stored and rendered: it must not contain "<", so it can never close its style element
     *
     * @param string $customCss
     * @return bool
     */
    private function isAllowedCustomCss(string $customCss): bool
    {
        return !str_contains($customCss, '<');
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->readNonBlankString(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->readNonBlankString(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getExtensionAttributes(): ?SliderExtensionInterface
    {
        $extensionAttributes = $this->_getExtensionAttributes();

        return $extensionAttributes instanceof SliderExtensionInterface ? $extensionAttributes : null;
    }

    /**
     * @inheritDoc
     */
    public function setExtensionAttributes(SliderExtensionInterface $extensionAttributes): SliderInterface
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Cache tag of a location, or null when there is none or the stored code is not a valid location code
     *
     * @param string|null $location
     * @return string|null
     */
    private function locationTag(?string $location): ?string
    {
        if ($location === null || $location === '') {
            return null;
        }

        try {
            return (new LocationCode($location))->toCacheTag();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
