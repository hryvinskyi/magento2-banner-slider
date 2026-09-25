<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\VariantRows;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Magento\Framework\Model\ResourceModel\Db\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponsiveCropResource::class)]
class ResponsiveCropTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var VariantRows&MockObject
     */
    private MockObject $variantRows;

    /**
     * @var ResponsiveCropResource
     */
    private ResponsiveCropResource $resource;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->variantRows = $this->createMock(VariantRows::class);
        $this->resource = new ResponsiveCropResource($this->createMock(Context::class), $this->variantRows);
    }

    /**
     * The variants are attached after load
     *
     * @return void
     */
    public function testAfterLoadAttachesVariants(): void
    {
        $variant = new CropVariant('webp', 85);
        $crop = $this->newCrop();
        $crop->setCropId(3);
        $this->variantRows->method('fetchByCropIds')->with([3])->willReturn([3 => [$variant]]);

        $this->invoke('_afterLoad', $crop);

        self::assertSame([$variant], $crop->getVariants());
        self::assertTrue($crop->hasVariants());
    }

    /**
     * The variant rows are replaced after saving a crop that carries its variants
     *
     * @return void
     */
    public function testAfterSaveReplacesVariants(): void
    {
        $variant = new CropVariant('avif', 70);
        $crop = $this->newCrop();
        $crop->setCropId(3)->setVariants([$variant]);
        $this->variantRows->expects(self::once())->method('replace')->with(3, [$variant]);

        $this->invoke('_afterSave', $crop);
    }

    /**
     * A crop saved without its variants keeps the stored ones
     *
     * @return void
     */
    public function testAfterSaveWithoutVariantsKeepsRows(): void
    {
        $crop = $this->newCrop();
        $crop->setCropId(3);
        $this->variantRows->expects(self::never())->method('replace');

        $this->invoke('_afterSave', $crop);
    }

    /**
     * Call a protected hook of the resource model
     *
     * @param string $hook
     * @param ResponsiveCrop $crop
     * @return void
     */
    private function invoke(string $hook, ResponsiveCrop $crop): void
    {
        (new \ReflectionMethod($this->resource, $hook))->invoke($this->resource, $crop);
    }

    /**
     * A new crop model
     *
     * @return ResponsiveCrop
     */
    private function newCrop(): ResponsiveCrop
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();

        return new ResponsiveCrop(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(ResponsiveCropInterface::CROP_ID)
        );
    }
}
