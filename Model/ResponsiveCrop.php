<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSlider\Model\Data\MediaRelativePath;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\CropVariantInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropExtensionInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Magento\Framework\DataObject\IdentityInterface;

/**
 * Stored crop: typed accessors over a `hryvinskyi_banner_slider_responsive_crop` row and its variant rows.
 *
 * A stored rectangle of all zeros, or one with no width or height, reads as "no rectangle". The variants are held
 * under the `variants` data key; the crop resource model loads them and replaces the variant rows on save, but only
 * when the key is set, so saving a crop that never had its variants loaded or set keeps the stored ones.
 */
class ResponsiveCrop extends AbstractEntityModel implements ResponsiveCropInterface, IdentityInterface
{
    /**
     * Cache tag of every crop; `CACHE_TAG . '_' . <crop id>` tags one crop
     */
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
     * Cache tags of every page affected by this crop: its own tag and the tag of its banner
     *
     * @return list<string>
     */
    public function getIdentities(): array
    {
        $tags = [];
        $cropId = $this->getCropId();
        if ($cropId !== null) {
            $tags[] = self::CACHE_TAG . '_' . $cropId;
        }
        $bannerId = $this->getBannerId();
        if ($bannerId !== null) {
            $tags[] = BannerInterface::CACHE_TAG . '_' . $bannerId;
        }

        return $tags;
    }

    /**
     * @inheritDoc
     */
    public function getCropId(): ?int
    {
        return $this->readId(self::CROP_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCropId(int $cropId): ResponsiveCropInterface
    {
        $this->assertPositiveId('Crop id', $cropId);

        return $this->setData(self::CROP_ID, $cropId);
    }

    /**
     * @inheritDoc
     */
    public function getBannerId(): ?int
    {
        return $this->readId(self::BANNER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setBannerId(int $bannerId): ResponsiveCropInterface
    {
        $this->assertPositiveId('Crop banner id', $bannerId);

        return $this->setData(self::BANNER_ID, $bannerId);
    }

    /**
     * @inheritDoc
     */
    public function getBreakpointId(): ?int
    {
        return $this->readId(self::BREAKPOINT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setBreakpointId(int $breakpointId): ResponsiveCropInterface
    {
        $this->assertPositiveId('Crop breakpoint id', $breakpointId);

        return $this->setData(self::BREAKPOINT_ID, $breakpointId);
    }

    /**
     * @inheritDoc
     */
    public function getSourceImage(): ?string
    {
        return $this->readNonBlankString(self::SOURCE_IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setSourceImage(?string $sourceImage): ResponsiveCropInterface
    {
        return $this->setData(
            self::SOURCE_IMAGE,
            $sourceImage === null ? null : (new MediaRelativePath($sourceImage))->toString()
        );
    }

    /**
     * @inheritDoc
     */
    public function getCropRect(): ?CropRect
    {
        $x = max(0, $this->readInt(self::CROP_X) ?? 0);
        $y = max(0, $this->readInt(self::CROP_Y) ?? 0);
        $width = $this->readInt(self::CROP_WIDTH) ?? 0;
        $height = $this->readInt(self::CROP_HEIGHT) ?? 0;
        if ($width < 1 || $height < 1) {
            return null;
        }

        return new CropRect($x, $y, $width, $height);
    }

    /**
     * @inheritDoc
     */
    public function setCropRect(?CropRect $rect): ResponsiveCropInterface
    {
        $this->setData(self::CROP_X, $rect?->getX() ?? 0);
        $this->setData(self::CROP_Y, $rect?->getY() ?? 0);
        $this->setData(self::CROP_WIDTH, $rect?->getWidth() ?? 0);

        return $this->setData(self::CROP_HEIGHT, $rect?->getHeight() ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function getCroppedImage(): ?string
    {
        return $this->readNonBlankString(self::CROPPED_IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setCroppedImage(?string $croppedImage): ResponsiveCropInterface
    {
        return $this->setData(
            self::CROPPED_IMAGE,
            $croppedImage === null ? null : (new MediaRelativePath($croppedImage))->toString()
        );
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
    public function setIsEnabled(bool $enabled): ResponsiveCropInterface
    {
        return $this->setData(self::STATUS, (int)$enabled);
    }

    /**
     * @inheritDoc
     */
    public function getVariants(): array
    {
        $stored = $this->getData(self::VARIANTS);
        if (!is_array($stored)) {
            return [];
        }

        $variants = [];
        foreach ($stored as $variant) {
            if ($variant instanceof CropVariantInterface) {
                $variants[] = $variant;
            }
        }

        return $variants;
    }

    /**
     * @inheritDoc
     */
    public function setVariants(array $variants): ResponsiveCropInterface
    {
        $formats = [];
        foreach ($variants as $variant) {
            $format = $variant->getFormat();
            if (isset($formats[$format])) {
                throw new \InvalidArgumentException(
                    sprintf('A crop may have one variant per format; "%s" appears twice.', $format)
                );
            }
            $formats[$format] = true;
        }

        return $this->setData(self::VARIANTS, $variants);
    }

    /**
     * Whether the variants were loaded or set, so saving the crop replaces its variant rows
     *
     * @return bool
     */
    public function hasVariants(): bool
    {
        return $this->hasData(self::VARIANTS);
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
    public function getExtensionAttributes(): ?ResponsiveCropExtensionInterface
    {
        $extensionAttributes = $this->_getExtensionAttributes();

        return $extensionAttributes instanceof ResponsiveCropExtensionInterface ? $extensionAttributes : null;
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
