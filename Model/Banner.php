<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSlider\Model\Banner\BannerUrlPolicy;
use Hryvinskyi\BannerSlider\Model\Data\MediaRelativePath;
use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerExtensionInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ActiveWindow;
use Hryvinskyi\BannerSliderApi\Api\Value\AspectRatio;
use Hryvinskyi\BannerSliderApi\Api\Value\AspectRatioParser;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Stored banner: typed accessors over a `hryvinskyi_banner_slider_banner` row.
 *
 * Getters never fail on stored data: a type that is not a known case reads as custom content, an unreadable aspect
 * ratio reads as 16:9, a partial image size reads as unknown, an inverted window reads as its start, and a link URL
 * the URL policy rejects reads as none and is logged (surrounding whitespace, which browsers drop from a link, is
 * trimmed first; see RejectedStoredValueLog). Setters guard the rules of their own field (URL schemes, relative
 * media paths); rules that depend on the banner type are the banner validator's.
 */
class Banner extends AbstractEntityModel implements BannerInterface, IdentityInterface
{
    /**
     * @var string
     */
    protected $_cacheTag = BannerInterface::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_banner';

    /**
     * @var string
     */
    protected $_eventObject = 'banner';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AspectRatioParser $aspectRatioParser
     * @param BannerUrlPolicy $urlPolicy
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
        private readonly AspectRatioParser $aspectRatioParser,
        private readonly BannerUrlPolicy $urlPolicy,
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
        $this->_init(ResourceModel\Banner::class);
    }

    /**
     * Cache tags of every page showing this banner: its own tag and the tag of its slider
     *
     * When the banner moves to another slider, the previous slider's tag is included too.
     *
     * @return list<string>
     */
    public function getIdentities(): array
    {
        $tags = [];
        $bannerId = $this->getBannerId();
        if ($bannerId !== null) {
            $tags[] = BannerInterface::CACHE_TAG . '_' . $bannerId;
        }

        $originalSliderId = $this->getOrigData(self::SLIDER_ID);
        $sliderIds = [
            $this->getSliderId(),
            is_numeric($originalSliderId) ? (int)$originalSliderId : null,
        ];
        foreach (array_unique(array_filter($sliderIds, fn (?int $id): bool => $id !== null && $id > 0)) as $id) {
            $tags[] = SliderInterface::CACHE_TAG . '_' . $id;
        }

        return $tags;
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
    public function setBannerId(int $bannerId): BannerInterface
    {
        $this->assertPositiveId('Banner id', $bannerId);

        return $this->setData(self::BANNER_ID, $bannerId);
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
    public function setSliderId(int $sliderId): BannerInterface
    {
        $this->assertPositiveId('Banner slider id', $sliderId);

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
    public function setName(string $name): BannerInterface
    {
        $this->assertNotBlank('Banner name', $name);

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
    public function setIsEnabled(bool $enabled): BannerInterface
    {
        return $this->setData(self::STATUS, (int)$enabled);
    }

    /**
     * What the banner shows; image when none is set, custom content for a stored value that is not a known type
     *
     * @return BannerType
     */
    public function getType(): BannerType
    {
        if ($this->getData(self::TYPE) === null) {
            return BannerType::IMAGE;
        }
        $stored = $this->readInt(self::TYPE);

        return $stored === null ? BannerType::CUSTOM : BannerType::tryFrom($stored) ?? BannerType::CUSTOM;
    }

    /**
     * @inheritDoc
     */
    public function setType(BannerType $type): BannerInterface
    {
        return $this->setData(self::TYPE, $type->value);
    }

    /**
     * @inheritDoc
     */
    public function getContent(): ?string
    {
        return $this->readString(self::CONTENT);
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
    public function getImage(): ?string
    {
        return $this->readNonBlankString(self::IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setImage(?string $image): BannerInterface
    {
        return $this->setData(self::IMAGE, $image === null ? null : (new MediaRelativePath($image))->toString());
    }

    /**
     * @inheritDoc
     */
    public function getImageDimensions(): ?Dimensions
    {
        $width = $this->readInt(self::IMAGE_WIDTH);
        $height = $this->readInt(self::IMAGE_HEIGHT);
        if ($width === null || $height === null || $width < 1 || $height < 1) {
            return null;
        }

        return new Dimensions($width, $height);
    }

    /**
     * @inheritDoc
     */
    public function setImageDimensions(?Dimensions $dimensions): BannerInterface
    {
        $this->setData(self::IMAGE_WIDTH, $dimensions?->getWidth());

        return $this->setData(self::IMAGE_HEIGHT, $dimensions?->getHeight());
    }

    /**
     * @inheritDoc
     */
    public function getVideoUrl(): ?string
    {
        return $this->readNonBlankString(self::VIDEO_URL);
    }

    /**
     * @inheritDoc
     */
    public function setVideoUrl(?string $videoUrl): BannerInterface
    {
        if ($videoUrl !== null) {
            $this->urlPolicy->assertVideoUrl($videoUrl);
        }

        return $this->setData(self::VIDEO_URL, $videoUrl);
    }

    /**
     * @inheritDoc
     */
    public function getVideoPath(): ?string
    {
        return $this->readNonBlankString(self::VIDEO_PATH);
    }

    /**
     * @inheritDoc
     */
    public function setVideoPath(?string $videoPath): BannerInterface
    {
        return $this->setData(
            self::VIDEO_PATH,
            $videoPath === null ? null : (new MediaRelativePath($videoPath))->toString()
        );
    }

    /**
     * @inheritDoc
     */
    public function getVideoAspectRatio(): AspectRatio
    {
        $stored = $this->readNonBlankString(self::VIDEO_ASPECT_RATIO);
        if ($stored !== null) {
            try {
                return $this->aspectRatioParser->parse($stored);
            } catch (\InvalidArgumentException) {
                return $this->defaultAspectRatio();
            }
        }

        return $this->defaultAspectRatio();
    }

    /**
     * @inheritDoc
     */
    public function setVideoAspectRatio(AspectRatio $aspectRatio): BannerInterface
    {
        return $this->setData(self::VIDEO_ASPECT_RATIO, $aspectRatio->toString());
    }

    /**
     * @inheritDoc
     */
    public function isVideoAsBackground(): bool
    {
        return $this->readBool(self::VIDEO_AS_BACKGROUND, false);
    }

    /**
     * @inheritDoc
     */
    public function setVideoAsBackground(bool $asBackground): BannerInterface
    {
        return $this->setData(self::VIDEO_AS_BACKGROUND, (int)$asBackground);
    }

    /**
     * @inheritDoc
     */
    public function getLinkUrl(): ?string
    {
        $linkUrl = $this->readNonBlankString(self::LINK_URL);
        if ($linkUrl === null) {
            return null;
        }
        $linkUrl = trim($linkUrl);
        try {
            $this->urlPolicy->assertLinkUrl($linkUrl);
        } catch (\InvalidArgumentException $exception) {
            $this->rejectedValueLog->report('banner', $this->getBannerId(), self::LINK_URL, $exception->getMessage());

            return null;
        }

        return $linkUrl;
    }

    /**
     * @inheritDoc
     */
    public function setLinkUrl(?string $linkUrl): BannerInterface
    {
        if ($linkUrl !== null) {
            $this->urlPolicy->assertLinkUrl($linkUrl);
        }

        return $this->setData(self::LINK_URL, $linkUrl);
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): ?string
    {
        return $this->readNonBlankString(self::TITLE);
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
        return $this->readBool(self::OPEN_IN_NEW_TAB, true);
    }

    /**
     * @inheritDoc
     */
    public function setOpenInNewTab(bool $openInNewTab): BannerInterface
    {
        return $this->setData(self::OPEN_IN_NEW_TAB, (int)$openInNewTab);
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
    public function setActiveWindow(ActiveWindow $window): BannerInterface
    {
        $this->storeActiveWindow($window, self::FROM_DATE, self::TO_DATE);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPosition(): int
    {
        return max(0, $this->readInt(self::POSITION) ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function setPosition(int $position): BannerInterface
    {
        $this->assertNotNegative('Banner position', $position);

        return $this->setData(self::POSITION, $position);
    }

    /**
     * @inheritDoc
     */
    public function isPreloadEnabled(): bool
    {
        return $this->readBool(self::IS_PRELOAD, false);
    }

    /**
     * @inheritDoc
     */
    public function setPreloadEnabled(bool $enabled): BannerInterface
    {
        return $this->setData(self::IS_PRELOAD, (int)$enabled);
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
    public function getExtensionAttributes(): ?BannerExtensionInterface
    {
        $extensionAttributes = $this->_getExtensionAttributes();

        return $extensionAttributes instanceof BannerExtensionInterface ? $extensionAttributes : null;
    }

    /**
     * @inheritDoc
     */
    public function setExtensionAttributes(BannerExtensionInterface $extensionAttributes): BannerInterface
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * The ratio used when none can be read
     *
     * @return AspectRatio
     */
    private function defaultAspectRatio(): AspectRatio
    {
        return new AspectRatio(AspectRatio::DEFAULT_WIDTH, AspectRatio::DEFAULT_HEIGHT);
    }
}
