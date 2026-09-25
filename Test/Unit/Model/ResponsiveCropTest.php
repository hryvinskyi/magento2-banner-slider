<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponsiveCrop::class)]
class ResponsiveCropTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var ResponsiveCrop
     */
    private ResponsiveCrop $crop;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();
        $this->crop = new ResponsiveCrop(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(ResponsiveCropInterface::CROP_ID)
        );
    }

    /**
     * Valid values pass the setters and read back
     *
     * @return void
     */
    public function testSetters(): void
    {
        $this->crop->setCropId(11)
            ->setBannerId(3)
            ->setBreakpointId(7)
            ->setSourceImage('banner_slider/image/source.jpg')
            ->setCropRect(new CropRect(10, 20, 300, 200))
            ->setCroppedImage('banner_slider/responsive/3/desktop_abc.jpg')
            ->setIsEnabled(false);

        self::assertSame(11, $this->crop->getCropId());
        self::assertSame(3, $this->crop->getBannerId());
        self::assertSame(7, $this->crop->getBreakpointId());
        self::assertSame('banner_slider/image/source.jpg', $this->crop->getSourceImage());
        $rect = $this->crop->getCropRect();
        self::assertNotNull($rect);
        self::assertTrue((new CropRect(10, 20, 300, 200))->equals($rect));
        self::assertSame('banner_slider/responsive/3/desktop_abc.jpg', $this->crop->getCroppedImage());
        self::assertFalse($this->crop->isEnabled());

        $this->crop->setSourceImage(null)->setCroppedImage(null)->setCropRect(null);
        self::assertNull($this->crop->getSourceImage());
        self::assertNull($this->crop->getCroppedImage());
        self::assertNull($this->crop->getCropRect());
        self::assertSame(0, $this->crop->getData(ResponsiveCropInterface::CROP_WIDTH));
    }

    /**
     * A stored rectangle without width or height reads as no rectangle
     *
     * @param string $width
     * @param string $height
     * @return void
     */
    #[TestWith(['0', '0'])]
    #[TestWith(['100', '0'])]
    #[TestWith(['0', '100'])]
    public function testEmptyStoredRectReadsAsNull(string $width, string $height): void
    {
        $this->crop->setData(ResponsiveCropInterface::CROP_WIDTH, $width);
        $this->crop->setData(ResponsiveCropInterface::CROP_HEIGHT, $height);

        self::assertNull($this->crop->getCropRect());
    }

    /**
     * Variants read back as set; the flag tells whether they were loaded or set
     *
     * @return void
     */
    public function testVariants(): void
    {
        self::assertFalse($this->crop->hasVariants());
        self::assertSame([], $this->crop->getVariants());

        $webp = new CropVariant('webp', 85, 'banner_slider/responsive/3/desktop_abc.webp');
        $avif = new CropVariant('avif', 80);
        $this->crop->setVariants([$webp, $avif]);

        self::assertTrue($this->crop->hasVariants());
        self::assertSame([$webp, $avif], $this->crop->getVariants());
    }

    /**
     * Two variants of one format are rejected
     *
     * @return void
     */
    public function testDuplicateVariantFormatIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->crop->setVariants([new CropVariant('webp', 85), new CropVariant('webp', 70)]);
    }

    /**
     * Stored data that is not a variant list reads as no variants
     *
     * @return void
     */
    public function testForeignVariantDataIsIgnored(): void
    {
        $this->crop->setData(ResponsiveCropInterface::VARIANTS, ['webp', new CropVariant('avif', 80)]);

        self::assertCount(1, $this->crop->getVariants());
    }

    /**
     * Each setter rejects a value that breaks its field's rule
     *
     * @param string $setter
     * @param int|string $value
     * @return void
     */
    #[TestWith(['setCropId', 0])]
    #[TestWith(['setBannerId', 0])]
    #[TestWith(['setBreakpointId', -1])]
    #[TestWith(['setSourceImage', '/tmp/a.jpg'])]
    #[TestWith(['setCroppedImage', ''])]
    public function testSetterGuards(string $setter, int|string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $callable = [$this->crop, $setter];
        self::assertIsCallable($callable);
        $callable($value);
    }

    /**
     * Identities hold the crop tag and its banner's tag
     *
     * @return void
     */
    public function testIdentities(): void
    {
        $this->crop->setCropId(11)->setBannerId(3);

        self::assertSame(
            [ResponsiveCrop::CACHE_TAG . '_11', BannerInterface::CACHE_TAG . '_3'],
            $this->crop->getIdentities()
        );
    }
}
