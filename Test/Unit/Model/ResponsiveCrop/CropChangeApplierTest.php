<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChange;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeApplier;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileLedger;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputPlan;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropWriteResult;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropWriter;
use Hryvinskyi\BannerSlider\Test\Unit\Model\ImageFormats;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropChangeApplier::class)]
#[CoversClass(CropChange::class)]
class CropChangeApplierTest extends TestCase
{
    use ImageFormats;

    /**
     * Every call, in order
     *
     * @var list<string>
     */
    private array $log = [];

    /**
     * @var ResponsiveCropRepositoryInterface&MockObject
     */
    private MockObject $cropRepository;

    /**
     * @var ResponsiveCropInterface&MockObject
     */
    private MockObject $newCrop;

    /**
     * @var CropChangeApplier
     */
    private CropChangeApplier $applier;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $writer = $this->createMock(CropWriter::class);
        $writer->method('write')->willReturnCallback(
            function (
                ?ResponsiveCropInterface $current,
                CropOutputPlan $plan,
                int $bannerId,
                string $identifier
            ): CropWriteResult {
                $this->log[] = sprintf('write %s of banner %d', $identifier, $bannerId);

                return new CropWriteResult(
                    'r/' . $identifier . '.png',
                    [new CropVariant('webp', 85, 'r/' . $identifier . '.webp')],
                    ['r/' . $identifier . '.png'],
                    $current === null ? [] : ['r/old-' . $identifier . '.png']
                );
            }
        );
        $this->cropRepository = $this->createMock(ResponsiveCropRepositoryInterface::class);
        $this->cropRepository->method('save')->willReturnCallback(
            function (ResponsiveCropInterface $crop): ResponsiveCropInterface {
                $this->log[] = 'save crop';

                return $crop;
            }
        );
        $this->cropRepository->method('delete')->willReturnCallback(function (): void {
            $this->log[] = 'delete crop';
        });
        $this->newCrop = $this->createMock(ResponsiveCropInterface::class);
        $factory = $this->createMock(ResponsiveCropInterfaceFactory::class);
        $factory->method('create')->willReturn($this->newCrop);

        $this->applier = new CropChangeApplier($writer, $this->cropRepository, $factory);
    }

    /**
     * Every file is written before any crop row changes; new crops get the banner and the desired state
     *
     * @return void
     */
    public function testWritesAllFilesBeforeRows(): void
    {
        $stored = $this->createMock(ResponsiveCropInterface::class);
        $removed = $this->createMock(ResponsiveCropInterface::class);
        $this->newCrop->expects(self::once())->method('setBannerId')->with(5);
        $this->newCrop->expects(self::once())->method('setBreakpointId')->with(4);
        $this->newCrop->expects(self::once())->method('setSourceImage')->with('banner_slider/image/b.png');
        $this->newCrop->expects(self::once())->method('setIsEnabled')->with(false);
        $this->newCrop->expects(self::once())->method('setCroppedImage')->with('r/bp4.png');
        $stored->expects(self::once())->method('setCroppedImage')->with('r/bp3.png');
        $ledger = new CropFileLedger();

        $saved = $this->applier->apply(
            [
                $this->change(3, $stored, null, true),
                $this->change(4, null, 'banner_slider/image/b.png', false),
                new CropChange(
                    new CropInput(6, null, null, [], [], false, true),
                    $this->breakpoint(6),
                    $removed,
                    null
                ),
            ],
            $this->banner(5),
            $ledger
        );

        self::assertSame(2, $saved);
        self::assertSame(
            ['write bp3 of banner 5', 'write bp4 of banner 5', 'save crop', 'save crop', 'delete crop'],
            $this->log
        );
        self::assertSame(['r/bp3.png', 'r/bp4.png'], $ledger->getCreatedPaths());
        self::assertSame(['r/bp3.png', 'r/bp3.webp', 'r/bp4.png', 'r/bp4.webp'], $ledger->getNewPaths());
        self::assertSame(['r/old-bp3.png'], $ledger->getObsoletePaths());
    }

    /**
     * Removing a crop that does not exist changes nothing
     *
     * @return void
     */
    public function testRemovingMissingCropIsNoOp(): void
    {
        $this->cropRepository->expects(self::never())->method('delete');

        self::assertSame(0, $this->applier->apply(
            [new CropChange(new CropInput(6, null, null, [], [], false, true), $this->breakpoint(6), null, null)],
            $this->banner(5),
            new CropFileLedger()
        ));
    }

    /**
     * A failed delete is reported as a failed save naming the breakpoint
     *
     * @return void
     */
    public function testFailedDelete(): void
    {
        $this->cropRepository = $this->createMock(ResponsiveCropRepositoryInterface::class);
        $this->cropRepository->method('delete')->willThrowException(new CouldNotDeleteException(__('locked')));
        $applier = new CropChangeApplier(
            $this->createMock(CropWriter::class),
            $this->cropRepository,
            $this->createMock(ResponsiveCropInterfaceFactory::class)
        );

        $this->expectExceptionObject(new CouldNotSaveException(
            __('The crop for breakpoint "%1" could not be deleted.', 'bp6')
        ));
        $applier->apply(
            [new CropChange(
                new CropInput(6, null, null, [], [], false, true),
                $this->breakpoint(6),
                $this->createMock(ResponsiveCropInterface::class),
                null
            )],
            $this->banner(5),
            new CropFileLedger()
        );
    }

    /**
     * Released crops are deleted through the repository, which removes their files once the deletion is committed
     *
     * @return void
     */
    public function testReleaseDeletesCrops(): void
    {
        $first = $this->createMock(ResponsiveCropInterface::class);
        $second = $this->createMock(ResponsiveCropInterface::class);

        $this->applier->release([$first, $second]);

        self::assertSame(['delete crop', 'delete crop'], $this->log);
    }

    /**
     * A released crop that cannot be deleted fails the save, naming its breakpoint
     *
     * @return void
     */
    public function testFailedRelease(): void
    {
        $cropRepository = $this->createMock(ResponsiveCropRepositoryInterface::class);
        $cropRepository->method('delete')->willThrowException(new CouldNotDeleteException(__('locked')));
        $applier = new CropChangeApplier(
            $this->createMock(CropWriter::class),
            $cropRepository,
            $this->createMock(ResponsiveCropInterfaceFactory::class)
        );
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->method('getBreakpointId')->willReturn(9);

        $this->expectExceptionObject(new CouldNotSaveException(
            __('The crop for breakpoint %1 that no longer applies could not be deleted.', 9)
        ));
        $applier->release([$crop]);
    }

    /**
     * Crops cannot be saved for a banner without an id
     *
     * @return void
     */
    public function testUnsavedBanner(): void
    {
        $this->expectException(CouldNotSaveException::class);

        $this->applier->apply([], $this->banner(null), new CropFileLedger());
    }

    /**
     * A saved change for a breakpoint
     *
     * @param int $breakpointId
     * @param ResponsiveCropInterface|null $current
     * @param string|null $source
     * @param bool $enabled
     * @return CropChange
     */
    private function change(
        int $breakpointId,
        ?ResponsiveCropInterface $current,
        ?string $source,
        bool $enabled
    ): CropChange {
        return new CropChange(
            new CropInput($breakpointId, $source, new CropRect(0, 0, 10, 10), [], [], $enabled, false),
            $this->breakpoint($breakpointId),
            $current,
            new CropOutputPlan(
                new MediaImage('banner_slider/image/b.png', new Dimensions(100, 100), $this->format('png')),
                new CropRect(0, 0, 10, 10),
                new Dimensions(10, 10),
                $this->format('png'),
                [],
                []
            )
        );
    }

    /**
     * A breakpoint identified as "bp" and its id
     *
     * @param int $breakpointId
     * @return BreakpointInterface
     */
    private function breakpoint(int $breakpointId): BreakpointInterface
    {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('getIdentifier')->willReturn('bp' . $breakpointId);

        return $breakpoint;
    }

    /**
     * A banner with an id, or unsaved
     *
     * @param int|null $bannerId
     * @return BannerInterface
     */
    private function banner(?int $bannerId): BannerInterface
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getBannerId')->willReturn($bannerId);

        return $banner;
    }
}
