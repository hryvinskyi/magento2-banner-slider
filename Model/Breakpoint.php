<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointExtensionInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Breakpoint model
 */
class Breakpoint extends AbstractExtensibleModel implements BreakpointInterface, IdentityInterface
{
    public const CACHE_TAG = 'hryvinskyi_banner_slider_breakpoint';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_breakpoint';

    /**
     * @var string
     */
    protected $_eventObject = 'breakpoint';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Breakpoint::class);
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
    public function getBreakpointId(): ?int
    {
        $id = $this->getData(self::BREAKPOINT_ID);
        return $id !== null ? (int)$id : null;
    }

    /**
     * @inheritDoc
     */
    public function setBreakpointId(?int $breakpointId): BreakpointInterface
    {
        return $this->setData(self::BREAKPOINT_ID, $breakpointId);
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
    public function setSliderId(?int $sliderId): BreakpointInterface
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
    public function setName(?string $name): BreakpointInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getIdentifier(): ?string
    {
        return $this->getData(self::IDENTIFIER);
    }

    /**
     * @inheritDoc
     */
    public function setIdentifier(?string $identifier): BreakpointInterface
    {
        return $this->setData(self::IDENTIFIER, $identifier);
    }

    /**
     * @inheritDoc
     */
    public function getMediaQuery(): ?string
    {
        return $this->getData(self::MEDIA_QUERY);
    }

    /**
     * @inheritDoc
     */
    public function setMediaQuery(?string $mediaQuery): BreakpointInterface
    {
        return $this->setData(self::MEDIA_QUERY, $mediaQuery);
    }

    /**
     * @inheritDoc
     */
    public function getMinWidth(): ?int
    {
        $minWidth = $this->getData(self::MIN_WIDTH);
        return $minWidth !== null ? (int)$minWidth : null;
    }

    /**
     * @inheritDoc
     */
    public function setMinWidth(?int $minWidth): BreakpointInterface
    {
        return $this->setData(self::MIN_WIDTH, $minWidth);
    }

    /**
     * @inheritDoc
     */
    public function getTargetWidth(): ?int
    {
        $targetWidth = $this->getData(self::TARGET_WIDTH);
        return $targetWidth !== null ? (int)$targetWidth : null;
    }

    /**
     * @inheritDoc
     */
    public function setTargetWidth(?int $targetWidth): BreakpointInterface
    {
        return $this->setData(self::TARGET_WIDTH, $targetWidth);
    }

    /**
     * @inheritDoc
     */
    public function getTargetHeight(): ?int
    {
        $targetHeight = $this->getData(self::TARGET_HEIGHT);
        return $targetHeight !== null ? (int)$targetHeight : null;
    }

    /**
     * @inheritDoc
     */
    public function setTargetHeight(?int $targetHeight): BreakpointInterface
    {
        return $this->setData(self::TARGET_HEIGHT, $targetHeight);
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
    public function setSortOrder(?int $sortOrder): BreakpointInterface
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
    public function setStatus(?int $status): BreakpointInterface
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
    public function setCreatedAt(?string $createdAt): BreakpointInterface
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
    public function setUpdatedAt(?string $updatedAt): BreakpointInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getAspectRatio(): float
    {
        $width = $this->getTargetWidth();
        $height = $this->getTargetHeight();

        if ($width === null || $height === null || $height === 0) {
            return 1.0;
        }

        return (float)$width / (float)$height;
    }
}
