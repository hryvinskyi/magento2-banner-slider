<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResourceModel;

use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use Hryvinskyi\BannerSlider\Model\Data\ResponsiveItemsCodec;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\VisibilityLinks;
use Hryvinskyi\BannerSlider\Model\Slider;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Visibility;
use Magento\Framework\Model\ResourceModel\Db\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(SliderResource::class)]
class SliderTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var VisibilityLinks&MockObject
     */
    private MockObject $visibilityLinks;

    /**
     * @var SliderResource
     */
    private SliderResource $resource;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->visibilityLinks = $this->createMock(VisibilityLinks::class);
        $this->resource = new SliderResource($this->createMock(Context::class), $this->visibilityLinks);
    }

    /**
     * The link lists are attached after load
     *
     * @return void
     */
    public function testAfterLoadAttachesLinks(): void
    {
        $slider = $this->newSlider();
        $this->visibilityLinks->expects(self::once())->method('attach')->with([$slider]);

        $this->invoke('_afterLoad', $slider);
    }

    /**
     * The link rows are replaced after saving a slider that carries its visibility
     *
     * @return void
     */
    public function testAfterSaveReplacesLinks(): void
    {
        $visibility = new Visibility([0], [1], false);
        $slider = $this->newSlider();
        $slider->setSliderId(8)->setVisibility($visibility);
        $this->visibilityLinks->expects(self::once())->method('replace')->with(
            8,
            self::callback(fn (Visibility $saved): bool => $saved->equals($visibility))
        );

        $this->invoke('_afterSave', $slider);
    }

    /**
     * A slider saved without its store list keeps the stored links
     *
     * @return void
     */
    public function testAfterSaveWithoutVisibilityKeepsLinks(): void
    {
        $slider = $this->newSlider();
        $slider->setSliderId(8);
        $this->visibilityLinks->expects(self::never())->method('replace');

        $this->invoke('_afterSave', $slider);
    }

    /**
     * Call a protected hook of the resource model
     *
     * @param string $hook
     * @param Slider $slider
     * @return void
     */
    private function invoke(string $hook, Slider $slider): void
    {
        (new \ReflectionMethod($this->resource, $hook))->invoke($this->resource, $slider);
    }

    /**
     * A new slider model
     *
     * @return Slider
     */
    private function newSlider(): Slider
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();

        return new Slider(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            new ResponsiveItemsCodec(),
            new RejectedStoredValueLog(new NullLogger()),
            $this->modelResource(SliderInterface::SLIDER_ID)
        );
    }
}
