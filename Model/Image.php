<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\ImageInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Banner image model
 */
class Image extends AbstractModel implements ImageInterface, IdentityInterface
{
    public const CACHE_TAG = 'hryvinskyi_banner_slider_image';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_image';

    /**
     * @var string
     */
    protected $_eventObject = 'image';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Image::class);
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        $identities = [self::CACHE_TAG . '_' . $this->getId()];

        if ($this->getBannerId()) {
            $identities[] = Banner::CACHE_TAG . '_' . $this->getBannerId();
        }

        return $identities;
    }

    /**
     * @inheritDoc
     */
    public function getImageId(): ?int
    {
        $id = $this->getData(self::IMAGE_ID);
        return $id !== null ? (int)$id : null;
    }

    /**
     * @inheritDoc
     */
    public function setImageId(?int $imageId): ImageInterface
    {
        return $this->setData(self::IMAGE_ID, $imageId);
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
    public function setBannerId(?int $bannerId): ImageInterface
    {
        return $this->setData(self::BANNER_ID, $bannerId);
    }

    /**
     * @inheritDoc
     */
    public function getAlt(): ?string
    {
        return $this->getData(self::ALT);
    }

    /**
     * @inheritDoc
     */
    public function setAlt(?string $alt): ImageInterface
    {
        return $this->setData(self::ALT, $alt);
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
    public function setTitle(?string $title): ImageInterface
    {
        return $this->setData(self::TITLE, $title);
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
    public function setStatus(?int $status): ImageInterface
    {
        return $this->setData(self::STATUS, $status);
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
    public function setImage(?string $image): ImageInterface
    {
        return $this->setData(self::IMAGE, $image);
    }

    /**
     * @inheritDoc
     */
    public function getPictureMedia(): ?string
    {
        return $this->getData(self::PICTURE_MEDIA);
    }

    /**
     * @inheritDoc
     */
    public function setPictureMedia(?string $pictureMedia): ImageInterface
    {
        return $this->setData(self::PICTURE_MEDIA, $pictureMedia);
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
    public function setCreatedAt(?string $createdAt): ImageInterface
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
    public function setUpdatedAt(?string $updatedAt): ImageInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
