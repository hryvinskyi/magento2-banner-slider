<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropExtensionInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Responsive crop model
 */
class ResponsiveCrop extends AbstractExtensibleModel implements ResponsiveCropInterface, IdentityInterface
{
    public const CACHE_TAG = 'hryvinskyi_banner_slider_responsive_crop';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_responsive_crop';

    /**
     * @var string
     */
    protected $_eventObject = 'responsive_crop';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\ResponsiveCrop::class);
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
    public function getCropId(): ?int
    {
        $id = $this->getData(self::CROP_ID);
        return $id !== null ? (int)$id : null;
    }

    /**
     * @inheritDoc
     */
    public function setCropId(?int $cropId): ResponsiveCropInterface
    {
        return $this->setData(self::CROP_ID, $cropId);
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
    public function setBannerId(?int $bannerId): ResponsiveCropInterface
    {
        return $this->setData(self::BANNER_ID, $bannerId);
    }

    /**
     * @inheritDoc
     */
    public function getBreakpointId(): ?int
    {
        $id = $this->getData(self::BREAKPOINT_ID);
        return $id !== null ? (int)$id : null;
    }

    /**
     * @inheritDoc
     */
    public function setBreakpointId(?int $breakpointId): ResponsiveCropInterface
    {
        return $this->setData(self::BREAKPOINT_ID, $breakpointId);
    }

    /**
     * @inheritDoc
     */
    public function getSourceImage(): ?string
    {
        return $this->getData(self::SOURCE_IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setSourceImage(?string $sourceImage): ResponsiveCropInterface
    {
        return $this->setData(self::SOURCE_IMAGE, $sourceImage);
    }

    /**
     * @inheritDoc
     */
    public function getCropX(): ?int
    {
        $value = $this->getData(self::CROP_X);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setCropX(?int $cropX): ResponsiveCropInterface
    {
        return $this->setData(self::CROP_X, $cropX);
    }

    /**
     * @inheritDoc
     */
    public function getCropY(): ?int
    {
        $value = $this->getData(self::CROP_Y);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setCropY(?int $cropY): ResponsiveCropInterface
    {
        return $this->setData(self::CROP_Y, $cropY);
    }

    /**
     * @inheritDoc
     */
    public function getCropWidth(): ?int
    {
        $value = $this->getData(self::CROP_WIDTH);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setCropWidth(?int $cropWidth): ResponsiveCropInterface
    {
        return $this->setData(self::CROP_WIDTH, $cropWidth);
    }

    /**
     * @inheritDoc
     */
    public function getCropHeight(): ?int
    {
        $value = $this->getData(self::CROP_HEIGHT);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setCropHeight(?int $cropHeight): ResponsiveCropInterface
    {
        return $this->setData(self::CROP_HEIGHT, $cropHeight);
    }

    /**
     * @inheritDoc
     */
    public function getCroppedImage(): ?string
    {
        return $this->getData(self::CROPPED_IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setCroppedImage(?string $croppedImage): ResponsiveCropInterface
    {
        return $this->setData(self::CROPPED_IMAGE, $croppedImage);
    }

    /**
     * @inheritDoc
     */
    public function getWebpImage(): ?string
    {
        return $this->getData(self::WEBP_IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setWebpImage(?string $webpImage): ResponsiveCropInterface
    {
        return $this->setData(self::WEBP_IMAGE, $webpImage);
    }

    /**
     * @inheritDoc
     */
    public function getAvifImage(): ?string
    {
        return $this->getData(self::AVIF_IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setAvifImage(?string $avifImage): ResponsiveCropInterface
    {
        return $this->setData(self::AVIF_IMAGE, $avifImage);
    }

    /**
     * @inheritDoc
     */
    public function isGenerateWebpEnabled(): bool
    {
        return (bool)$this->getData(self::GENERATE_WEBP);
    }

    /**
     * @inheritDoc
     */
    public function setGenerateWebpEnabled(bool $generateWebp): ResponsiveCropInterface
    {
        return $this->setData(self::GENERATE_WEBP, $generateWebp);
    }

    /**
     * @inheritDoc
     */
    public function isGenerateAvifEnabled(): bool
    {
        return (bool)$this->getData(self::GENERATE_AVIF);
    }

    /**
     * @inheritDoc
     */
    public function setGenerateAvifEnabled(bool $generateAvif): ResponsiveCropInterface
    {
        return $this->setData(self::GENERATE_AVIF, $generateAvif);
    }

    /**
     * @inheritDoc
     */
    public function getWebpQuality(): ?int
    {
        $value = $this->getData(self::WEBP_QUALITY);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setWebpQuality(?int $webpQuality): ResponsiveCropInterface
    {
        return $this->setData(self::WEBP_QUALITY, $webpQuality);
    }

    /**
     * @inheritDoc
     */
    public function getAvifQuality(): ?int
    {
        $value = $this->getData(self::AVIF_QUALITY);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function setAvifQuality(?int $avifQuality): ResponsiveCropInterface
    {
        return $this->setData(self::AVIF_QUALITY, $avifQuality);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): ?int
    {
        $sortOrder = $this->getData(self::SORT_ORDER);
        return $sortOrder !== null ? (int)$sortOrder : null;
    }

    /**
     * @inheritDoc
     */
    public function setSortOrder(?int $sortOrder): ResponsiveCropInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
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
    public function setStatus(?int $status): ResponsiveCropInterface
    {
        return $this->setData(self::STATUS, $status);
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
    public function setCreatedAt(?string $createdAt): ResponsiveCropInterface
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
    public function setUpdatedAt(?string $updatedAt): ResponsiveCropInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getExtensionAttributes(): ?ResponsiveCropExtensionInterface
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritDoc
     */
    public function setExtensionAttributes(
        ResponsiveCropExtensionInterface $extensionAttributes
    ): ResponsiveCropInterface {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
