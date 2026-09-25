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
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointInput;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Magento\Framework\DataObject\IdentityInterface;

/**
 * Stored breakpoint: typed accessors over a `hryvinskyi_banner_slider_breakpoint` row.
 *
 * A stored target height of 0 or less reads as null (keep the crop's aspect ratio). `toSpec()` never fails on a
 * stored row: an empty identifier reads as `breakpoint-<id>` and a target width below 1 as 1, so rendering code keeps
 * working until the breakpoint is corrected; the breakpoint validator rejects such a row on its next save.
 */
class Breakpoint extends AbstractEntityModel implements BreakpointInterface, IdentityInterface
{
    /**
     * Cache tag of every breakpoint; `CACHE_TAG . '_' . <breakpoint id>` tags one breakpoint
     */
    public const CACHE_TAG = 'hryvinskyi_banner_slider_breakpoint';

    /**
     * Largest target width and height in pixels; a crop bigger than this is never rendered
     */
    public const MAX_TARGET_SIZE = 5000;

    private const MEDIA_QUERY_FORBIDDEN = ['<', '{', '}'];

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
     * Cache tags of every page affected by this breakpoint: its own tag and the tag of its slider
     *
     * @return list<string>
     */
    public function getIdentities(): array
    {
        $tags = [];
        $breakpointId = $this->getBreakpointId();
        if ($breakpointId !== null) {
            $tags[] = self::CACHE_TAG . '_' . $breakpointId;
        }
        $sliderId = $this->getSliderId();
        if ($sliderId !== null) {
            $tags[] = SliderInterface::CACHE_TAG . '_' . $sliderId;
        }

        return $tags;
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
    public function setBreakpointId(int $breakpointId): BreakpointInterface
    {
        $this->assertPositiveId('Breakpoint id', $breakpointId);

        return $this->setData(self::BREAKPOINT_ID, $breakpointId);
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
    public function setSliderId(int $sliderId): BreakpointInterface
    {
        $this->assertPositiveId('Breakpoint slider id', $sliderId);

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
    public function setName(string $name): BreakpointInterface
    {
        $this->assertNotBlank('Breakpoint name', $name);

        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return $this->readString(self::IDENTIFIER) ?? '';
    }

    /**
     * @inheritDoc
     */
    public function setIdentifier(string $identifier): BreakpointInterface
    {
        if (preg_match(BreakpointInput::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'Breakpoint identifier must be 1-50 lowercase letters, digits, "_" or "-", starting with a letter'
                . ' or digit, got "%s".',
                $identifier
            ));
        }

        return $this->setData(self::IDENTIFIER, $identifier);
    }

    /**
     * @inheritDoc
     */
    public function getMediaQuery(): string
    {
        return $this->readString(self::MEDIA_QUERY) ?? '';
    }

    /**
     * @inheritDoc
     */
    public function setMediaQuery(string $mediaQuery): BreakpointInterface
    {
        $this->assertNotBlank('Breakpoint media query', $mediaQuery);
        foreach (self::MEDIA_QUERY_FORBIDDEN as $character) {
            if (str_contains($mediaQuery, $character)) {
                throw new \InvalidArgumentException(
                    sprintf('Breakpoint media query must not contain "%s".', $character)
                );
            }
        }

        return $this->setData(self::MEDIA_QUERY, $mediaQuery);
    }

    /**
     * @inheritDoc
     */
    public function getMinWidth(): int
    {
        return max(0, $this->readInt(self::MIN_WIDTH) ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function setMinWidth(int $minWidth): BreakpointInterface
    {
        $this->assertNotNegative('Breakpoint min width', $minWidth);

        return $this->setData(self::MIN_WIDTH, $minWidth);
    }

    /**
     * @inheritDoc
     */
    public function getTargetWidth(): int
    {
        return $this->readInt(self::TARGET_WIDTH) ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function setTargetWidth(int $targetWidth): BreakpointInterface
    {
        $this->assertPositiveId('Breakpoint target width', $targetWidth);
        $this->assertTargetSize('Breakpoint target width', $targetWidth);

        return $this->setData(self::TARGET_WIDTH, $targetWidth);
    }

    /**
     * @inheritDoc
     */
    public function getTargetHeight(): ?int
    {
        return $this->readId(self::TARGET_HEIGHT);
    }

    /**
     * @inheritDoc
     */
    public function setTargetHeight(?int $targetHeight): BreakpointInterface
    {
        if ($targetHeight !== null) {
            $this->assertPositiveId('Breakpoint target height', $targetHeight);
            $this->assertTargetSize('Breakpoint target height', $targetHeight);
        }

        return $this->setData(self::TARGET_HEIGHT, $targetHeight);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return $this->readInt(self::SORT_ORDER) ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function setSortOrder(int $sortOrder): BreakpointInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
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
    public function setIsEnabled(bool $enabled): BreakpointInterface
    {
        return $this->setData(self::STATUS, (int)$enabled);
    }

    /**
     * The rendering view of this breakpoint; placeholders stand in for an empty identifier or a missing width
     *
     * @return BreakpointSpec
     */
    public function toSpec(): BreakpointSpec
    {
        $identifier = $this->getIdentifier();
        if ($identifier === '') {
            $identifier = 'breakpoint-' . ($this->getBreakpointId() ?? 0);
        }

        return new BreakpointSpec(
            $identifier,
            $this->getMediaQuery(),
            $this->getMinWidth(),
            max(1, $this->getTargetWidth()),
            $this->getTargetHeight()
        );
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
    public function getExtensionAttributes(): ?BreakpointExtensionInterface
    {
        $extensionAttributes = $this->_getExtensionAttributes();

        return $extensionAttributes instanceof BreakpointExtensionInterface ? $extensionAttributes : null;
    }

    /**
     * @inheritDoc
     */
    public function setExtensionAttributes(BreakpointExtensionInterface $extensionAttributes): BreakpointInterface
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Reject a target side larger than the largest crop rendered
     *
     * @param string $label
     * @param int $size
     * @return void
     * @throws \InvalidArgumentException
     */
    private function assertTargetSize(string $label, int $size): void
    {
        if ($size > self::MAX_TARGET_SIZE) {
            throw new \InvalidArgumentException(
                sprintf('%s must be at most %d pixels, got %d.', $label, self::MAX_TARGET_SIZE, $size)
            );
        }
    }
}
