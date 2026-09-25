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
use Hryvinskyi\BannerSlider\Model\Image\MediaImageReader;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChange;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeApplier;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeSet;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeValidator;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileLedger;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileTransaction;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropRegenerationArea;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropRegenerator;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\WholeImageCropArea;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CropRegenerator::class)]
#[CoversClass(CropRegenerationArea::class)]
class CropRegeneratorTest extends TestCase
{
    /**
     * Inputs checked, in order
     *
     * @var list<CropInput>
     */
    private array $checked = [];

    /**
     * Breakpoint ids whose change fails when applied
     *
     * @var list<int>
     */
    private array $failing = [];

    /**
     * Number of checks run
     *
     * @var int
     */
    private int $checks = 0;

    /**
     * Number of transactions run
     *
     * @var int
     */
    private int $transactions = 0;

    /**
     * @var BannerRepositoryInterface&MockObject
     */
    private MockObject $bannerRepository;

    /**
     * @var ResponsiveCropRepositoryInterface&MockObject
     */
    private MockObject $cropRepository;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var BreakpointRepositoryInterface&MockObject
     */
    private MockObject $breakpointRepository;

    /**
     * @var MediaImageReader&MockObject
     */
    private MockObject $imageReader;

    /**
     * @var CropRegenerator
     */
    private CropRegenerator $regenerator;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->bannerRepository = $this->createMock(BannerRepositoryInterface::class);
        $this->cropRepository = $this->createMock(ResponsiveCropRepositoryInterface::class);
        $validator = $this->createMock(CropChangeValidator::class);
        $validator->method('check')->willReturnCallback(
            function (BannerInterface $banner, array $inputs, array $stored, ?string $storedImage): CropChangeSet {
                self::assertSame($banner->getImage(), $storedImage);
                $this->checks++;
                $changes = [];
                $errors = [];
                foreach ($inputs as $input) {
                    self::assertInstanceOf(CropInput::class, $input);
                    $current = $stored[$input->getBreakpointId()] ?? null;
                    self::assertInstanceOf(ResponsiveCropInterface::class, $current);
                    $this->checked[] = $input;
                    if ($input->getBreakpointId() === 99) {
                        $errors[] = __('Breakpoint 99 does not belong to the slider of this banner.');
                        continue;
                    }
                    $changes[] = new CropChange($input, $this->createMock(BreakpointInterface::class), $current, null);
                }

                return new CropChangeSet($changes, $errors);
            }
        );
        $applier = $this->createMock(CropChangeApplier::class);
        $applier->method('apply')->willReturnCallback(function (array $changes): int {
            $change = $changes[0];
            self::assertInstanceOf(CropChange::class, $change);
            if (in_array($change->getInput()->getBreakpointId(), $this->failing, true)) {
                throw new CouldNotSaveException(__('disk full'));
            }

            return 1;
        });
        $transaction = $this->createMock(CropFileTransaction::class);
        $transaction->method('run')->willReturnCallback(function (callable $work): mixed {
            $this->transactions++;

            return $work(new CropFileLedger());
        });
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->breakpointRepository = $this->createMock(BreakpointRepositoryInterface::class);
        $this->imageReader = $this->createMock(MediaImageReader::class);

        $this->regenerator = new CropRegenerator(
            $this->bannerRepository,
            $this->cropRepository,
            new CropRegenerationArea(
                $this->breakpointRepository,
                $this->imageReader,
                new WholeImageCropArea(new CropTargetSize())
            ),
            $validator,
            $applier,
            $transaction,
            $this->logger
        );
    }

    /**
     * Every crop with a rectangle is regenerated from its stored source, rectangle and variant qualities
     *
     * @return void
     */
    public function testRegeneratesCropsWithRectangle(): void
    {
        $this->bannerRepository->method('getById')->with(5)->willReturn($this->createMock(BannerInterface::class));
        $this->cropRepository->method('getByBannerId')->with(5)->willReturn([
            $this->crop(3, new CropRect(1, 2, 30, 40), 'legacy_slider/image/a.jpg', false),
            $this->crop(4, null, null, true),
            $this->crop(6, new CropRect(0, 0, 10, 10), null, true),
        ]);

        self::assertSame(2, $this->regenerator->regenerate(5));
        self::assertSame(1, $this->checks);
        self::assertSame(2, $this->transactions);
        self::assertCount(2, $this->checked);
        $first = $this->checked[0];
        self::assertSame(3, $first->getBreakpointId());
        self::assertSame('legacy_slider/image/a.jpg', $first->getSourceImage());
        self::assertTrue((new CropRect(1, 2, 30, 40))->equals($first->getRect() ?? new CropRect(0, 0, 1, 1)));
        self::assertSame([['webp', 70]], array_map(
            static fn ($request): array => [$request->getFormatCode(), $request->getQuality()],
            $first->getFormats()
        ));
        self::assertSame([], $first->getEncodedImages());
        self::assertFalse($first->isEnabled());
        self::assertFalse($first->shouldRemove());
    }

    /**
     * With a breakpoint only that crop is regenerated
     *
     * @return void
     */
    public function testOneBreakpoint(): void
    {
        $this->bannerRepository->method('getById')->willReturn($this->createMock(BannerInterface::class));
        $this->cropRepository->method('getByBannerId')->willReturn([
            $this->crop(3, new CropRect(0, 0, 10, 10), null, true),
            $this->crop(6, new CropRect(0, 0, 10, 10), null, true),
        ]);

        self::assertSame(1, $this->regenerator->regenerate(5, 6));
        self::assertSame(6, $this->checked[0]->getBreakpointId());
    }

    /**
     * A failing crop does not stop the others; every failure is logged and named at the end
     *
     * @return void
     */
    public function testFailuresDoNotStopTheOthers(): void
    {
        $this->failing = [3];
        $this->bannerRepository->method('getById')->willReturn($this->createMock(BannerInterface::class));
        $this->cropRepository->method('getByBannerId')->willReturn([
            $this->crop(3, new CropRect(0, 0, 10, 10), null, true),
            $this->crop(99, new CropRect(0, 0, 10, 10), null, true),
            $this->crop(6, new CropRect(0, 0, 10, 10), null, true),
        ]);
        $this->logger->expects(self::exactly(2))->method('error');

        try {
            $this->regenerator->regenerate(5);
            self::fail('The failures must be reported.');
        } catch (CouldNotSaveException $exception) {
            self::assertSame(
                'Some crops of banner 5 could not be regenerated: '
                . 'Breakpoint 99 does not belong to the slider of this banner.; breakpoint 3: disk full',
                $exception->getMessage()
            );
        }
        self::assertSame(
            [3, 99, 6],
            array_map(static fn (CropInput $input): int => $input->getBreakpointId(), $this->checked)
        );
        self::assertSame(2, $this->transactions);
    }

    /**
     * A crop an earlier version stored as its source itself is cut from the whole-image area of that source
     *
     * @return void
     */
    public function testSourceAsOutputCropIsCutFromTheWholeImageArea(): void
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getImage')->willReturn('legacy_slider/image/desktop.jpg');
        $this->bannerRepository->method('getById')->willReturn($banner);
        $mobile = 'legacy_slider/image/mobile.jpg';
        $this->cropRepository->method('getByBannerId')->willReturn([
            $this->crop(3, new CropRect(0, 0, 892, 588), $mobile, true, $mobile),
            $this->crop(4, new CropRect(0, 0, 1920, 294), null, true, 'legacy_slider/image/desktop.jpg'),
            $this->crop(6, new CropRect(5, 5, 100, 50), null, true, 'banner_slider/responsive/5/desktop_a.jpg'),
        ]);
        $this->imageReader->method('read')->willReturnCallback(
            fn (string $path): MediaImage => new MediaImage(
                $path,
                $path === $mobile ? new Dimensions(1784, 1000) : new Dimensions(3840, 600),
                new ImageFormat('jpeg', 'image/jpeg', 'jpg')
            )
        );
        $this->breakpointRepository->method('getById')->willReturnCallback(
            fn (int $breakpointId): BreakpointInterface => $this->breakpoint(
                $breakpointId === 3
                    ? new BreakpointSpec('mobile', '(max-width: 767px)', 0, 892, 588)
                    : new BreakpointSpec('desktop', '(min-width: 768px)', 768, 1920, 294)
            )
        );

        self::assertSame(3, $this->regenerator->regenerate(5));

        $areas = [];
        foreach ($this->checked as $input) {
            $rect = $input->getRect();
            self::assertNotNull($rect);
            $areas[$input->getBreakpointId()] = [$rect->getX(), $rect->getY(), $rect->getWidth(), $rect->getHeight()];
        }
        self::assertSame([3 => [133, 0, 1517, 1000], 4 => [0, 6, 3840, 588], 6 => [5, 5, 100, 50]], $areas);
    }

    /**
     * A whole-image crop whose source cannot be read fails alone, logged, and the others are still regenerated
     *
     * @return void
     */
    public function testUnreadableWholeImageSourceFailsThatCropOnly(): void
    {
        $this->bannerRepository->method('getById')->willReturn($this->createMock(BannerInterface::class));
        $gone = 'legacy_slider/image/gone.jpg';
        $this->cropRepository->method('getByBannerId')->willReturn([
            $this->crop(3, new CropRect(0, 0, 892, 588), $gone, true, $gone),
            $this->crop(6, new CropRect(0, 0, 10, 10), null, true),
        ]);
        $this->imageReader->method('read')->willThrowException(
            new FileSystemException(__('The media file "%1" does not exist.', $gone))
        );
        $this->logger->expects(self::once())->method('error');

        try {
            $this->regenerator->regenerate(5);
            self::fail('The failure must be reported.');
        } catch (CouldNotSaveException $exception) {
            self::assertStringContainsString('breakpoint 3: The media file', $exception->getMessage());
        }
        self::assertSame(
            [6],
            array_map(static fn (CropInput $input): int => $input->getBreakpointId(), $this->checked)
        );
        self::assertSame(1, $this->transactions);
    }

    /**
     * An unknown banner is reported as such
     *
     * @return void
     */
    public function testUnknownBanner(): void
    {
        $this->bannerRepository->method('getById')->willThrowException(new NoSuchEntityException(__('gone')));

        $this->expectException(NoSuchEntityException::class);
        $this->regenerator->regenerate(5);
    }

    /**
     * A stored crop with a WebP variant at quality 70
     *
     * @param int $breakpointId
     * @param CropRect|null $rect
     * @param string|null $source
     * @param bool $enabled
     * @param string|null $output The crop's output file
     * @return ResponsiveCropInterface
     */
    private function crop(
        int $breakpointId,
        ?CropRect $rect,
        ?string $source,
        bool $enabled,
        ?string $output = null
    ): ResponsiveCropInterface {
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->method('getBreakpointId')->willReturn($breakpointId);
        $crop->method('getCropRect')->willReturn($rect);
        $crop->method('getSourceImage')->willReturn($source);
        $crop->method('getCroppedImage')->willReturn($output);
        $crop->method('isEnabled')->willReturn($enabled);
        $crop->method('getVariants')->willReturn([new CropVariant('webp', 70, 'banner_slider/responsive/5/x.webp')]);

        return $crop;
    }

    /**
     * A breakpoint with the given rendering view
     *
     * @param BreakpointSpec $spec
     * @return BreakpointInterface
     */
    private function breakpoint(BreakpointSpec $spec): BreakpointInterface
    {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('toSpec')->willReturn($spec);

        return $breakpoint;
    }
}
