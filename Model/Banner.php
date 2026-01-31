<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerExtensionInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Banner model
 */
class Banner extends AbstractExtensibleModel implements BannerInterface, IdentityInterface
{
    public const CACHE_TAG = 'hryvinskyi_banner_slider_banner';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_banner';

    /**
     * @var string
     */
    protected $_eventObject = 'banner';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Banner::class);
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        $identities = [self::CACHE_TAG . '_' . $this->getId()];

        if ($this->getSliderId()) {
            $identities[] = Slider::CACHE_TAG . '_' . $this->getSliderId();
        }

        return $identities;
    }

    /**
     * @inheritDoc
     */
    public function getBannerId(): ?int
    {
        $id = $this->getData(self::BANNER_ID);
        return $id !== null ? (int)$id : null;
    }

    /**
     * @inheritDoc
     */
    public function setBannerId(?int $bannerId): BannerInterface
    {
        return $this->setData(self::BANNER_ID, $bannerId);
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
    public function setSliderId(?int $sliderId): BannerInterface
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
    public function setName(?string $name): BannerInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getImage(): ?string
    {
        return $this->getData(self::IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setImage(?string $image): BannerInterface
    {
        return $this->setData(self::IMAGE, $image);
    }

    /**
     * @inheritDoc
     */
    public function getVideoUrl(): ?string
    {
        return $this->getData(self::VIDEO_URL);
    }

    /**
     * @inheritDoc
     */
    public function setVideoUrl(?string $videoUrl): BannerInterface
    {
        return $this->setData(self::VIDEO_URL, $videoUrl);
    }

    /**
     * @inheritDoc
     */
    public function getVideoAspectRatio(): ?string
    {
        return $this->getData(self::VIDEO_ASPECT_RATIO);
    }

    /**
     * @inheritDoc
     */
    public function setVideoAspectRatio(?string $videoAspectRatio): BannerInterface
    {
        return $this->setData(self::VIDEO_ASPECT_RATIO, $videoAspectRatio);
    }

    /**
     * @inheritDoc
     */
    public function getVideoPath(): ?string
    {
        return $this->getData(self::VIDEO_PATH);
    }

    /**
     * @inheritDoc
     */
    public function setVideoPath(?string $videoPath): BannerInterface
    {
        return $this->setData(self::VIDEO_PATH, $videoPath);
    }

    /**
     * @inheritDoc
     */
    public function isVideoAsBackground(): bool
    {
        return (bool)$this->getData(self::VIDEO_AS_BACKGROUND);
    }

    /**
     * @inheritDoc
     */
    public function setVideoAsBackground(bool $videoAsBackground): BannerInterface
    {
        return $this->setData(self::VIDEO_AS_BACKGROUND, $videoAsBackground);
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
    public function setStatus(?int $status): BannerInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getType(): ?int
    {
        $type = $this->getData(self::TYPE);
        return $type !== null ? (int)$type : null;
    }

    /**
     * @inheritDoc
     */
    public function setType(?int $type): BannerInterface
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getContent(): ?string
    {
        return $this->getData(self::CONTENT);
    }

    /**
     * @inheritDoc
     */
    public function setContent(?string $content): BannerInterface
    {
        return $this->setData(self::CONTENT, $content);
    }

    /**
     * @inheritDoc
     */
    public function getLinkUrl(): ?string
    {
        return $this->getData(self::LINK_URL);
    }

    /**
     * @inheritDoc
     */
    public function setLinkUrl(?string $linkUrl): BannerInterface
    {
        return $this->setData(self::LINK_URL, $linkUrl);
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): ?string
    {
        return $this->getData(self::TITLE);
    }

    /**
     * @inheritDoc
     */
    public function setTitle(?string $title): BannerInterface
    {
        return $this->setData(self::TITLE, $title);
    }

    /**
     * @inheritDoc
     */
    public function isOpenInNewTab(): bool
    {
        return (bool)$this->getData(self::OPEN_IN_NEW_TAB);
    }

    /**
     * @inheritDoc
     */
    public function setOpenInNewTab(bool $openInNewTab): BannerInterface
    {
        return $this->setData(self::OPEN_IN_NEW_TAB, $openInNewTab);
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
    public function setCreatedAt(?string $createdAt): BannerInterface
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
    public function setUpdatedAt(?string $updatedAt): BannerInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
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
    public function setFromDate(?string $fromDate): BannerInterface
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
    public function setToDate(?string $toDate): BannerInterface
    {
        return $this->setData(self::TO_DATE, $toDate);
    }

    /**
     * @inheritDoc
     */
    public function getPosition(): ?int
    {
        $position = $this->getData(self::POSITION);
        return $position !== null ? (int)$position : null;
    }

    /**
     * @inheritDoc
     */
    public function setPosition(?int $position): BannerInterface
    {
        return $this->setData(self::POSITION, $position);
    }

    /**
     * @inheritDoc
     */
    public function isPreloadEnabled(): bool
    {
        return (bool)$this->getData(self::IS_PRELOAD);
    }

    /**
     * @inheritDoc
     */
    public function setIsPreload(bool $isPreload): BannerInterface
    {
        return $this->setData(self::IS_PRELOAD, $isPreload);
    }

    /**
     * @inheritDoc
     */
    public function getExtensionAttributes(): ?BannerExtensionInterface
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritDoc
     */
    public function setExtensionAttributes(BannerExtensionInterface $extensionAttributes): BannerInterface
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
