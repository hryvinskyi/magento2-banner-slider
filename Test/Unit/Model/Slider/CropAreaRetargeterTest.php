<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Slider;

use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop;
use Hryvinskyi\BannerSlider\Model\Slider\CropAreaRetargeter;
use Hryvinskyi\BannerSlider\Model\Slider\CropRetargetPlan;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCrop\CropRegeneratorInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Validation\ResponsiveCropValidatorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CropAreaRetargeter::class)]
#[CoversClass(CropRetargetPlan::class)]
class CropAreaRetargeterTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var Collection&MockObject
     */
    private MockObject $collection;

    /**
     * @var ResponsiveCropRepositoryInterface&MockObject
     */
    private MockObject $cropRepository;

    /**
     * @var ResponsiveCropValidatorInterface&MockObject
     */
    private MockObject $cropValidator;

    /**
     * @var CropRegeneratorInterface&MockObject
     */
    private MockObject $regenerator;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var CropAreaRetargeter
     */
    private CropAreaRetargeter $retargeter;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('addBreakpointIdsFilter')->willReturnSelf();
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->collection);
        $this->cropRepository = $this->createMock(ResponsiveCropRepositoryInterface::class);
        $this->cropValidator = $this->createMock(ResponsiveCropValidatorInterface::class);
        $this->regenerator = $this->createMock(CropRegeneratorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->retargeter = new CropAreaRetargeter(
            $collectionFactory,
            $this->cropRepository,
            $this->cropValidator,
            $this->regenerator,
            $this->logger
        );
    }

    /**
     * A crop of a breakpoint with a new fixed target gets the largest centred part of its area with the new aspect
     * ratio; an area that already has it, or a breakpoint with an open height, keeps its area; every crop with an area
     * is rendered again, a crop without one is not
     *
     * @return void
     */
    public function testPlan(): void
    {
        $wide = $this->crop(4, 1, new CropRect(0, 0, 1600, 800));
        $alreadyFitting = $this->crop(5, 1, new CropRect(100, 50, 960, 300));
        $withoutArea = $this->crop(6, 1, null);
        $openHeight = $this->crop(7, 2, new CropRect(10, 20, 300, 500));
        $this->collection->expects(self::once())->method('addBreakpointIdsFilter')->with([1, 2])->willReturnSelf();
        $this->collection->method('getItems')->willReturn([$wide, $alreadyFitting, $withoutArea, $openHeight]);

        $plan = $this->retargeter->plan([
            $this->breakpoint(1, 1920, 600),
            $this->breakpoint(2, 767, null),
        ]);

        self::assertSame([$wide], $plan->getCropsToSave());
        self::assertSame([0, 150, 1600, 500], $this->area($wide));
        self::assertSame([100, 50, 960, 300], $this->area($alreadyFitting));
        self::assertSame([10, 20, 300, 500], $this->area($openHeight));
        self::assertSame(
            [
                ['bannerId' => 4, 'breakpointId' => 1],
                ['bannerId' => 5, 'breakpointId' => 1],
                ['bannerId' => 7, 'breakpointId' => 2],
            ],
            $plan->getRegenerations()
        );
    }

    /**
     * A taller target keeps the full height and trims the sides, centred inside the current area
     *
     * @return void
     */
    public function testTallerTargetTrimsTheSides(): void
    {
        $crop = $this->crop(4, 1, new CropRect(200, 100, 1200, 600));
        $this->collection->method('getItems')->willReturn([$crop]);

        $this->retargeter->plan([$this->breakpoint(1, 600, 600)]);

        self::assertSame([500, 100, 600, 600], $this->area($crop));
    }

    /**
     * A new area the crop rules refuse is not applied: the crop keeps its area and the reason is logged
     *
     * @return void
     */
    public function testRefusedAreaKeepsTheCrop(): void
    {
        $crop = $this->crop(4, 1, new CropRect(0, 0, 1600, 800));
        $this->collection->method('getItems')->willReturn([$crop]);
        $this->cropValidator->method('validate')->willThrowException(
            new ValidationException(__('Breakpoint 1 belongs to another slider than banner 4.'))
        );
        $this->logger->expects(self::once())->method('warning')
            ->with(self::stringContains('Breakpoint 1 belongs to another slider than banner 4.'));

        $plan = $this->retargeter->plan([$this->breakpoint(1, 1920, 600)]);

        self::assertSame([], $plan->getCropsToSave());
        self::assertSame([0, 0, 1600, 800], $this->area($crop));
        self::assertSame([['bannerId' => 4, 'breakpointId' => 1]], $plan->getRegenerations());
    }

    /**
     * Nothing retargeted reads no crops
     *
     * @return void
     */
    public function testNothingRetargeted(): void
    {
        $this->collection->expects(self::never())->method('getItems');

        $plan = $this->retargeter->plan([$this->breakpoint(null, 1920, 600)]);

        self::assertSame([], $plan->getCropsToSave());
        self::assertSame([], $plan->getRegenerations());
    }

    /**
     * Applying saves the crops whose area changed
     *
     * @return void
     */
    public function testApplySavesTheChangedCrops(): void
    {
        $crop = $this->crop(4, 1, new CropRect(0, 150, 1600, 500));
        $this->cropRepository->expects(self::once())->method('save')->with($crop)->willReturn($crop);

        $this->retargeter->apply(new CropRetargetPlan([$crop], []));
    }

    /**
     * Every planned crop is rendered again for its breakpoint; a failure is logged and the others still run; the tags
     * of every banner concerned are returned once
     *
     * @return void
     */
    public function testRegenerateLogsFailures(): void
    {
        $calls = [];
        $this->regenerator->method('regenerate')->willReturnCallback(
            function (int $bannerId, ?int $breakpointId) use (&$calls): int {
                $calls[] = $bannerId . '/' . $breakpointId;
                if ($bannerId === 5) {
                    throw new CouldNotSaveException(__('disk full'));
                }

                return 1;
            }
        );
        $this->logger->expects(self::once())->method('error')
            ->with(self::stringContains('the crop of banner 5 for breakpoint 1 could not be rendered'));

        $tags = $this->retargeter->regenerate(new CropRetargetPlan([], [
            ['bannerId' => 4, 'breakpointId' => 1],
            ['bannerId' => 5, 'breakpointId' => 1],
            ['bannerId' => 4, 'breakpointId' => 2],
        ]));

        self::assertSame(['4/1', '5/1', '4/2'], $calls);
        self::assertSame(['hryvinskyi_banner_slider_banner_4', 'hryvinskyi_banner_slider_banner_5'], $tags);
    }

    /**
     * A stored breakpoint with its new target
     *
     * @param int|null $breakpointId
     * @param int $width
     * @param int|null $height
     * @return BreakpointInterface
     */
    private function breakpoint(?int $breakpointId, int $width, ?int $height): BreakpointInterface
    {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('getBreakpointId')->willReturn($breakpointId);
        $breakpoint->method('toSpec')->willReturn(new BreakpointSpec('bp', 'all', 0, $width, $height));

        return $breakpoint;
    }

    /**
     * A stored crop model of a banner for a breakpoint
     *
     * @param int $bannerId
     * @param int $breakpointId
     * @param CropRect|null $area
     * @return ResponsiveCrop
     */
    private function crop(int $bannerId, int $breakpointId, ?CropRect $area): ResponsiveCrop
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();
        $crop = new ResponsiveCrop(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(ResponsiveCropInterface::CROP_ID)
        );
        $crop->setCropId($bannerId * 10 + $breakpointId);
        $crop->setBannerId($bannerId);
        $crop->setBreakpointId($breakpointId);
        $crop->setCropRect($area);

        return $crop;
    }

    /**
     * The area of a crop as x, y, width, height
     *
     * @param ResponsiveCropInterface $crop
     * @return list<int>
     */
    private function area(ResponsiveCropInterface $crop): array
    {
        $area = $crop->getCropRect();
        self::assertNotNull($area);

        return [$area->getX(), $area->getY(), $area->getWidth(), $area->getHeight()];
    }
}
