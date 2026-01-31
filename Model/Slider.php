<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderExtensionInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Slider model
 */
class Slider extends AbstractExtensibleModel implements SliderInterface, IdentityInterface
{
    public const CACHE_TAG = 'hryvinskyi_banner_slider';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider';

    /**
     * @var string
     */
    protected $_eventObject = 'slider';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Slider::class);
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @inheritDoc
     */
    public function getSliderId(): ?int
    {
        $id = $this->getData(self::SLIDER_ID);
        return $id !== null ? (int)$id : null;
    }

    /**
     * @inheritDoc
     */
    public function setSliderId(?int $sliderId): SliderInterface
    {
        return $this->setData(self::SLIDER_ID, $sliderId);
    }

    /**
     * @inheritDoc
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName(?string $name): SliderInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): ?int
    {
        $status = $this->getData(self::STATUS);
        return $status !== null ? (int)$status : null;
    }

    /**
     * @inheritDoc
     */
    public function setStatus(?int $status): SliderInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getLocation(): ?string
    {
        return $this->getData(self::LOCATION);
    }

    /**
     * @inheritDoc
     */
    public function setLocation(?string $location): SliderInterface
    {
        return $this->setData(self::LOCATION, $location);
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): ?int
    {
        $priority = $this->getData(self::PRIORITY);
        return $priority !== null ? (int)$priority : null;
    }

    /**
     * @inheritDoc
     */
    public function setPriority(?int $priority): SliderInterface
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    /**
     * @inheritDoc
     */
    public function getEffect(): ?string
    {
        return $this->getData(self::EFFECT);
    }

    /**
     * @inheritDoc
     */
    public function setEffect(?string $effect): SliderInterface
    {
        return $this->setData(self::EFFECT, $effect);
    }

    /**
     * @inheritDoc
     */
    public function isAutoWidthEnabled(): bool
    {
        return (bool)$this->getData(self::AUTO_WIDTH);
    }

    /**
     * @inheritDoc
     */
    public function setAutoWidthEnabled(bool $autoWidth): SliderInterface
    {
        return $this->setData(self::AUTO_WIDTH, $autoWidth);
    }

    /**
     * Alias for setAutoWidthEnabled to support DataObjectHelper mapping
     *
     * @param bool $autoWidth
     * @return SliderInterface
     */
    public function setAutoWidth(bool $autoWidth): SliderInterface
    {
        return $this->setAutoWidthEnabled($autoWidth);
    }

    /**
     * @inheritDoc
     */
    public function isAutoHeightEnabled(): bool
    {
        return (bool)$this->getData(self::AUTO_HEIGHT);
    }

    /**
     * @inheritDoc
     */
    public function setAutoHeightEnabled(bool $autoHeight): SliderInterface
    {
        return $this->setData(self::AUTO_HEIGHT, $autoHeight);
    }

    /**
     * Alias for setAutoHeightEnabled to support DataObjectHelper mapping
     *
     * @param bool $autoHeight
     * @return SliderInterface
     */
    public function setAutoHeight(bool $autoHeight): SliderInterface
    {
        return $this->setAutoHeightEnabled($autoHeight);
    }

    /**
     * @inheritDoc
     */
    public function isLoopEnabled(): bool
    {
        return (bool)$this->getData(self::LOOP);
    }

    /**
     * @inheritDoc
     */
    public function setLoopEnabled(bool $loop): SliderInterface
    {
        return $this->setData(self::LOOP, $loop);
    }

    /**
     * Alias for setLoopEnabled to support DataObjectHelper mapping
     *
     * @param bool $loop
     * @return SliderInterface
     */
    public function setLoop(bool $loop): SliderInterface
    {
        return $this->setLoopEnabled($loop);
    }

    /**
     * @inheritDoc
     */
    public function isLazyLoadEnabled(): bool
    {
        return (bool)$this->getData(self::LAZY_LOAD);
    }

    /**
     * @inheritDoc
     */
    public function setLazyLoadEnabled(bool $lazyLoad): SliderInterface
    {
        return $this->setData(self::LAZY_LOAD, $lazyLoad);
    }

    /**
     * Alias for setLazyLoadEnabled to support DataObjectHelper mapping
     *
     * @param bool $lazyLoad
     * @return SliderInterface
     */
    public function setLazyLoad(bool $lazyLoad): SliderInterface
    {
        return $this->setLazyLoadEnabled($lazyLoad);
    }

    /**
     * @inheritDoc
     */
    public function isAutoplayEnabled(): bool
    {
        return (bool)$this->getData(self::AUTO_PLAY);
    }

    /**
     * @inheritDoc
     */
    public function setAutoplayEnabled(bool $autoplay): SliderInterface
    {
        return $this->setData(self::AUTO_PLAY, $autoplay);
    }

    /**
     * Alias for setAutoplayEnabled to support DataObjectHelper mapping
     *
     * @param bool $autoPlay
     * @return SliderInterface
     */
    public function setAutoPlay(bool $autoPlay): SliderInterface
    {
        return $this->setAutoplayEnabled($autoPlay);
    }

    /**
     * @inheritDoc
     */
    public function getAutoplayTimeout(): ?int
    {
        $timeout = $this->getData(self::AUTO_PLAY_TIMEOUT);
        return $timeout !== null ? (int)$timeout : null;
    }

    /**
     * @inheritDoc
     */
    public function setAutoplayTimeout(?int $autoplayTimeout): SliderInterface
    {
        return $this->setData(self::AUTO_PLAY_TIMEOUT, $autoplayTimeout);
    }

    /**
     * @inheritDoc
     */
    public function isNavigationEnabled(): bool
    {
        return (bool)$this->getData(self::NAV);
    }

    /**
     * @inheritDoc
     */
    public function setNavigationEnabled(bool $nav): SliderInterface
    {
        return $this->setData(self::NAV, $nav);
    }

    /**
     * Alias for setNavigationEnabled to support DataObjectHelper mapping
     *
     * @param bool $nav
     * @return SliderInterface
     */
    public function setNav(bool $nav): SliderInterface
    {
        return $this->setNavigationEnabled($nav);
    }

    /**
     * @inheritDoc
     */
    public function isPaginationEnabled(): bool
    {
        return (bool)$this->getData(self::DOTS);
    }

    /**
     * @inheritDoc
     */
    public function setPaginationEnabled(bool $dots): SliderInterface
    {
        return $this->setData(self::DOTS, $dots);
    }

    /**
     * Alias for setPaginationEnabled to support DataObjectHelper mapping
     *
     * @param bool $dots
     * @return SliderInterface
     */
    public function setDots(bool $dots): SliderInterface
    {
        return $this->setPaginationEnabled($dots);
    }

    /**
     * @inheritDoc
     */
    public function isResponsiveEnabled(): bool
    {
        return (bool)$this->getData(self::IS_RESPONSIVE);
    }

    /**
     * @inheritDoc
     */
    public function setResponsiveEnabled(bool $isResponsive): SliderInterface
    {
        return $this->setData(self::IS_RESPONSIVE, $isResponsive);
    }

    /**
     * Alias for setResponsiveEnabled to support DataObjectHelper mapping
     *
     * @param bool $isResponsive
     * @return SliderInterface
     */
    public function setIsResponsive(bool $isResponsive): SliderInterface
    {
        return $this->setResponsiveEnabled($isResponsive);
    }

    /**
     * @inheritDoc
     */
    public function getResponsiveItems(): ?string
    {
        return $this->getData(self::RESPONSIVE_ITEMS);
    }

    /**
     * @inheritDoc
     */
    public function setResponsiveItems(?string $responsiveItems): SliderInterface
    {
        return $this->setData(self::RESPONSIVE_ITEMS, $responsiveItems);
    }

    /**
     * @inheritDoc
     */
    public function getPreloadBannersCount(): int
    {
        return (int)$this->getData(self::PRELOAD_BANNERS_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setPreloadBannersCount(int $count): SliderInterface
    {
        return $this->setData(self::PRELOAD_BANNERS_COUNT, $count);
    }

    /**
     * @inheritDoc
     */
    public function getFromDate(): ?string
    {
        return $this->getData(self::FROM_DATE);
    }

    /**
     * @inheritDoc
     */
    public function setFromDate(?string $fromDate): SliderInterface
    {
        return $this->setData(self::FROM_DATE, $fromDate);
    }

    /**
     * @inheritDoc
     */
    public function getToDate(): ?string
    {
        return $this->getData(self::TO_DATE);
    }

    /**
     * @inheritDoc
     */
    public function setToDate(?string $toDate): SliderInterface
    {
        return $this->setData(self::TO_DATE, $toDate);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(?string $createdAt): SliderInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(?string $updatedAt): SliderInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getStoreIds(): string
    {
        return (string)$this->getData(self::STORE_IDS);
    }

    /**
     * @inheritDoc
     */
    public function setStoreIds(string $storeIds): SliderInterface
    {
        return $this->setData('store_ids', $storeIds);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerGroupIds(): string
    {
        return (string)$this->getData(self::CUSTOMER_GROUP_IDS);
    }

    /**
     * @inheritDoc
     */
    public function setCustomerGroupIds(string $customerGroupIds): SliderInterface
    {
        return $this->setData('customer_group_ids', $customerGroupIds);
    }

    /**
     * @inheritDoc
     */
    public function getExtensionAttributes(): ?SliderExtensionInterface
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritDoc
     */
    public function setExtensionAttributes(SliderExtensionInterface $extensionAttributes): SliderInterface
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
