<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Banner;

use Hryvinskyi\BannerSlider\Model\Banner;
use Hryvinskyi\BannerSlider\Model\Banner\BannerEditor;
use Hryvinskyi\BannerSlider\Model\Banner\BannerEditPlan;
use Hryvinskyi\BannerSlider\Model\Banner\BannerEditPlanner;
use Hryvinskyi\BannerSlider\Model\Banner\BannerImageSizer;
use Hryvinskyi\BannerSlider\Model\Banner\BannerUrlPolicy;
use Hryvinskyi\BannerSlider\Model\Cache\TagCleaner;
use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSlider\Model\Image\MediaImageReader;
use Hryvinskyi\BannerSlider\Model\Media\CommittedMediaRemover;
use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChange;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeApplier;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeValidator;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileStore;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileTransaction;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputPlan;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputPlanner;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropWriteResult;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropWriter;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\EncodedImageValidator;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\OriginalFormatRule;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSlider\Test\Unit\Model\ImageFormats;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Validation\BannerValidatorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\AspectRatioParser;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\FormatRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(BannerEditor::class)]
class BannerEditorTest extends TestCase
{
    use EntityModelArguments;
    use ImageFormats;

    /**
     * Every collaborator call, in order
     *
     * @var list<string>
     */
    private array $log = [];

    /**
     * @var ResourceConnection&MockObject
     */
    private MockObject $resourceConnection;

    /**
     * @var BannerEditPlanner&MockObject
     */
    private MockObject $planner;

    /**
     * @var BannerRepositoryInterface&MockObject
     */
    private MockObject $bannerRepository;

    /**
     * @var ResponsiveCropRepositoryInterface&MockObject
     */
    private MockObject $cropRepository;

    /**
     * @var CropChangeApplier
     */
    private CropChangeApplier $applier;

    /**
     * @var CropFileTransaction
     */
    private CropFileTransaction $transaction;

    /**
     * @var TagCleaner&MockObject
     */
    private MockObject $tagCleaner;

    /**
     * @var BannerEditor
     */
    private BannerEditor $editor;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        foreach (['beginTransaction', 'commit', 'rollBack'] as $method) {
            $connection->method($method)->willReturnCallback(function () use ($method, $connection) {
                $this->log[] = $method;

                return $connection;
            });
        }
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $fileStore = $this->createMock(CropFileStore::class);
        $fileStore->method('discard')->willReturnCallback(function (array $paths): void {
            $this->log[] = 'discard ' . $this->joined($paths);
        });
        $remover = $this->createMock(CommittedMediaRemover::class);
        $remover->method('removeAfterCommit')->willReturnCallback(function (array $paths, array $keep = []): void {
            $this->log[] = 'remove ' . $this->joined($paths) . ' keeping ' . $this->joined($keep);
        });
        $writer = $this->createMock(CropWriter::class);
        $writer->method('write')->willReturnCallback(
            function (
                ?ResponsiveCropInterface $current,
                CropOutputPlan $plan,
                int $bannerId,
                string $identifier
            ): CropWriteResult {
                $this->log[] = sprintf('write %s for banner %d', $identifier, $bannerId);

                return new CropWriteResult(
                    'banner_slider/responsive/12/desktop_new.png',
                    [],
                    ['banner_slider/responsive/12/desktop_new.png'],
                    ['banner_slider/responsive/12/desktop_old.png']
                );
            }
        );
        $this->cropRepository = $this->createMock(ResponsiveCropRepositoryInterface::class);
        $factory = $this->createMock(ResponsiveCropInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            fn (): ResponsiveCropInterface => $this->createMock(ResponsiveCropInterface::class)
        );
        $this->planner = $this->createMock(BannerEditPlanner::class);
        $this->bannerRepository = $this->createMock(BannerRepositoryInterface::class);
        $this->tagCleaner = $this->createMock(TagCleaner::class);
        $this->tagCleaner->method('clean')->willReturnCallback(function (array $tags): void {
            $this->log[] = 'clean ' . $this->joined($tags);
        });
        $this->applier = new CropChangeApplier($writer, $this->cropRepository, $factory);
        $this->transaction = new CropFileTransaction($this->resourceConnection, $fileStore, $remover);

        $this->editor = $this->editorWith($this->planner);
    }

    /**
     * Begin, save the banner (a new one gets its id), write files, save crops, commit, remove replaced files, and
     * only then clean the pages of the banner and its slider
     *
     * @return void
     */
    public function testSaveOrder(): void
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getBannerId')->willReturn(null);
        $banner->method('setImageDimensions')->willReturnCallback(
            function (?Dimensions $size) use ($banner): BannerInterface {
                $this->log[] = sprintf('size %dx%d', $size?->getWidth(), $size?->getHeight());

                return $banner;
            }
        );
        $saved = $this->savedBanner(12, 2);
        $inputs = [$this->input()];
        $this->planner->method('plan')->with($banner, $inputs)->willReturnCallback(function (): BannerEditPlan {
            $this->log[] = 'plan';

            return new BannerEditPlan(new Dimensions(1600, 800), [$this->change()]);
        });
        $this->bannerRepository->method('save')->with($banner)->willReturnCallback(
            function () use ($saved): BannerInterface {
                $this->log[] = 'save banner';

                return $saved;
            }
        );
        $this->cropRepository->method('save')->willReturnCallback(
            function (ResponsiveCropInterface $crop): ResponsiveCropInterface {
                $this->log[] = 'save crop';

                return $crop;
            }
        );

        self::assertSame($saved, $this->editor->save($banner, $inputs));
        self::assertSame(
            [
                'plan',
                'beginTransaction',
                'size 1600x800',
                'save banner',
                'write desktop for banner 12',
                'save crop',
                'commit',
                'remove banner_slider/responsive/12/desktop_old.png'
                    . ' keeping banner_slider/responsive/12/desktop_new.png',
                'clean hryvinskyi_banner_slider_banner_12,hryvinskyi_banner_slider_2',
            ],
            $this->log
        );
    }

    /**
     * Crops that no longer apply are deleted right after the banner is saved, before any new crop is written or
     * saved, so a new crop for the same breakpoint never meets the old row; the pages of both sliders are cleaned
     *
     * @return void
     */
    public function testStaleCropsAreReleasedBeforeNewCropsAreApplied(): void
    {
        $banner = $this->savedBanner(12, 2);
        $stale = $this->createMock(ResponsiveCropInterface::class);
        $this->planner->method('plan')->willReturn(new BannerEditPlan(null, [$this->change()], [$stale], 1));
        $this->bannerRepository->method('save')->willReturnCallback(
            function () use ($banner): BannerInterface {
                $this->log[] = 'save banner';

                return $banner;
            }
        );
        $this->cropRepository->expects(self::once())->method('delete')->with($stale)->willReturnCallback(
            function (): void {
                $this->log[] = 'delete stale crop';
            }
        );
        $this->cropRepository->method('save')->willReturnCallback(
            function (ResponsiveCropInterface $crop): ResponsiveCropInterface {
                $this->log[] = 'save crop';

                return $crop;
            }
        );

        $this->editor->save($banner, [$this->input()]);

        self::assertSame(
            [
                'beginTransaction',
                'save banner',
                'delete stale crop',
                'write desktop for banner 12',
                'save crop',
                'commit',
                'remove banner_slider/responsive/12/desktop_old.png'
                    . ' keeping banner_slider/responsive/12/desktop_new.png',
                'clean hryvinskyi_banner_slider_banner_12,hryvinskyi_banner_slider_2,hryvinskyi_banner_slider_1',
            ],
            $this->log
        );
    }

    /**
     * A failure after files were written rolls back, deletes only the files this save created, and cleans nothing
     *
     * @return void
     */
    public function testRollbackDeletesCreatedFilesOnly(): void
    {
        $banner = $this->savedBanner(12, 2);
        $this->planner->method('plan')->willReturn(new BannerEditPlan(null, [$this->change()]));
        $this->bannerRepository->method('save')->willReturn($banner);
        $this->cropRepository->method('save')->willThrowException(
            new CouldNotSaveException(__('The crop could not be saved.'))
        );

        try {
            $this->editor->save($banner, [$this->input()]);
            self::fail('The failure must surface.');
        } catch (CouldNotSaveException $exception) {
            self::assertSame('The crop could not be saved.', $exception->getMessage());
        }
        self::assertSame(
            [
                'beginTransaction',
                'write desktop for banner 12',
                'rollBack',
                'discard banner_slider/responsive/12/desktop_new.png',
            ],
            $this->log
        );
    }

    /**
     * After a rollback a new banner has no id again, so saving it again inserts it; an existing banner keeps its id
     *
     * @return void
     */
    public function testRollbackForgetsTheIdOfANewBanner(): void
    {
        $this->planner->method('plan')->willReturn(new BannerEditPlan(null, [$this->change()]));
        $this->bannerRepository->method('save')->willReturnCallback(
            static function (BannerInterface $banner): BannerInterface {
                if ($banner->getBannerId() === null) {
                    $banner->setBannerId(12);
                }

                return $banner;
            }
        );
        $this->cropRepository->method('save')->willThrowException(new CouldNotSaveException(__('disk full')));
        $new = $this->newBanner();
        $existing = $this->newBanner();
        $existing->setBannerId(7);

        foreach ([$new, $existing] as $banner) {
            try {
                $this->editor->save($banner, [$this->input()]);
                self::fail('The failure must surface.');
            } catch (CouldNotSaveException) {
                continue;
            }
        }

        self::assertNull($new->getBannerId());
        self::assertSame(7, $existing->getBannerId());
    }

    /**
     * An invalid save writes nothing and opens no transaction
     *
     * @return void
     */
    public function testInvalidSaveWritesNothing(): void
    {
        $error = __('The crop for breakpoint "desktop" may not use the image "customer/a.png".');
        $this->planner->method('plan')->willThrowException(
            new ValidationException($error, null, 0, new ValidationResult([$error]))
        );
        $this->bannerRepository->expects(self::never())->method('save');
        $this->tagCleaner->expects(self::never())->method('clean');

        try {
            $this->editor->save($this->createMock(BannerInterface::class), [$this->input()]);
            self::fail('The validation error must surface.');
        } catch (ValidationException) {
            self::assertSame([], $this->log);
        }
    }

    /**
     * A crop format that cannot be produced is found by the real planning chain before the transaction starts, so an
     * outer transaction (a data patch) is never rolled back by it
     *
     * @return void
     */
    public function testUnproducibleFormatFailsBeforeTheTransaction(): void
    {
        $breakpoint = $this->breakpoint();
        $breakpointRepository = $this->createMock(BreakpointRepositoryInterface::class);
        $breakpointRepository->method('getBySliderId')->willReturn([$breakpoint]);
        $imageReader = $this->createMock(MediaImageReader::class);
        $imageReader->method('read')->willReturn(
            new MediaImage('banner_slider/image/a.png', new Dimensions(1600, 800), $this->format('png'))
        );
        $registry = $this->formatRegistry();
        $planner = new BannerEditPlanner(
            $this->bannerRepository,
            $this->cropRepository,
            $this->createMock(BannerValidatorInterface::class),
            new BannerImageSizer($imageReader),
            new CropChangeValidator(
                $breakpointRepository,
                $imageReader,
                new MediaPaths(['image' => 'banner_slider/image', 'breakpoint' => 'banner_slider/breakpoint']),
                new CropOutputPlanner(
                    $registry,
                    new OriginalFormatRule($registry),
                    new CropTargetSize(),
                    $this->createMock(EncodedImageValidator::class),
                    $this->createMock(LoggerInterface::class)
                )
            ),
            new MediaPaths(['image' => 'banner_slider/image'])
        );
        $banner = $this->newBanner();
        $banner->setSliderId(2);
        $banner->setImage('banner_slider/image/a.png');
        $this->bannerRepository->expects(self::never())->method('save');

        try {
            $this->editorWith($planner)->save($banner, [new CropInput(
                3,
                null,
                new CropRect(0, 0, 800, 400),
                [new FormatRequest('avif', 60)],
                [],
                true,
                false
            )]);
            self::fail('A format no encoder can produce must be refused.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('cannot be produced as avif', $exception->getMessage());
        }
        self::assertSame([], $this->log);
    }

    /**
     * The editor over a planner
     *
     * @param BannerEditPlanner $planner
     * @return BannerEditor
     */
    private function editorWith(BannerEditPlanner $planner): BannerEditor
    {
        return new BannerEditor(
            $planner,
            $this->bannerRepository,
            $this->applier,
            $this->transaction,
            $this->tagCleaner
        );
    }

    /**
     * A crop input for breakpoint 3
     *
     * @return CropInput
     */
    private function input(): CropInput
    {
        return new CropInput(3, null, new CropRect(0, 0, 800, 400), [], [], true, false);
    }

    /**
     * A checked change for breakpoint 3 ("desktop")
     *
     * @return CropChange
     */
    private function change(): CropChange
    {
        return new CropChange(
            $this->input(),
            $this->breakpoint(),
            null,
            new CropOutputPlan(
                new MediaImage('banner_slider/image/a.png', new Dimensions(1600, 800), $this->format('png')),
                new CropRect(0, 0, 800, 400),
                new Dimensions(400, 200),
                $this->format('png'),
                [],
                []
            )
        );
    }

    /**
     * Breakpoint 3, "desktop", rendered at 400x200
     *
     * @return BreakpointInterface
     */
    private function breakpoint(): BreakpointInterface
    {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('getBreakpointId')->willReturn(3);
        $breakpoint->method('getIdentifier')->willReturn('desktop');
        $breakpoint->method('toSpec')->willReturn(new BreakpointSpec('desktop', '(min-width: 1200px)', 1200, 400, 200));

        return $breakpoint;
    }

    /**
     * A saved banner double with an id, on a slider
     *
     * @param int $bannerId
     * @param int $sliderId
     * @return BannerInterface
     */
    private function savedBanner(int $bannerId, int $sliderId): BannerInterface
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getBannerId')->willReturn($bannerId);
        $banner->method('getSliderId')->willReturn($sliderId);

        return $banner;
    }

    /**
     * A new banner model
     *
     * @return Banner
     */
    private function newBanner(): Banner
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();

        return new Banner(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            new AspectRatioParser(),
            new BannerUrlPolicy(),
            new RejectedStoredValueLog(new NullLogger()),
            $this->modelResource(BannerInterface::BANNER_ID)
        );
    }

    /**
     * The strings of a list, comma-separated
     *
     * @param array<mixed> $values
     * @return string
     */
    private function joined(array $values): string
    {
        return implode(',', array_filter($values, 'is_string'));
    }
}
