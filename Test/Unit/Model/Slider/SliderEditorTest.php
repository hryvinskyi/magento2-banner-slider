<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Slider;

use Hryvinskyi\BannerSlider\Model\Breakpoint;
use Hryvinskyi\BannerSlider\Model\Cache\TagCleaner;
use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use Hryvinskyi\BannerSlider\Model\Data\ResponsiveItemsCodec;
use Hryvinskyi\BannerSlider\Model\Slider;
use Hryvinskyi\BannerSlider\Model\Slider\BreakpointSetPlan;
use Hryvinskyi\BannerSlider\Model\Slider\BreakpointSetPlanner;
use Hryvinskyi\BannerSlider\Model\Slider\CropAreaRetargeter;
use Hryvinskyi\BannerSlider\Model\Slider\CropRetargetPlan;
use Hryvinskyi\BannerSlider\Model\Slider\SliderEditor;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\SliderRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointInput;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(SliderEditor::class)]
class SliderEditorTest extends TestCase
{
    use EntityModelArguments;

    /**
     * Every collaborator call, in order
     *
     * @var list<string>
     */
    private array $log = [];

    /**
     * @var SliderRepositoryInterface&MockObject
     */
    private MockObject $sliderRepository;

    /**
     * @var BreakpointRepositoryInterface&MockObject
     */
    private MockObject $breakpointRepository;

    /**
     * @var BreakpointSetPlanner&MockObject
     */
    private MockObject $planner;

    /**
     * @var CropAreaRetargeter&MockObject
     */
    private MockObject $retargeter;

    /**
     * @var TagCleaner&MockObject
     */
    private MockObject $tagCleaner;

    /**
     * @var ResourceConnection&MockObject
     */
    private MockObject $resourceConnection;

    /**
     * @var SliderEditor
     */
    private SliderEditor $editor;

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

        $this->sliderRepository = $this->createMock(SliderRepositoryInterface::class);
        $this->breakpointRepository = $this->createMock(BreakpointRepositoryInterface::class);
        $this->breakpointRepository->method('save')->willReturnCallback(
            function (BreakpointInterface $breakpoint): BreakpointInterface {
                $this->log[] = sprintf(
                    'save breakpoint %s as %s of slider %s',
                    $breakpoint->getBreakpointId() ?? 'new',
                    $breakpoint->getIdentifier(),
                    $breakpoint->getSliderId() ?? 'none'
                );

                return $breakpoint;
            }
        );
        $this->breakpointRepository->method('delete')->willReturnCallback(
            function (BreakpointInterface $breakpoint): void {
                $this->log[] = 'delete breakpoint ' . $breakpoint->getBreakpointId();
            }
        );
        $this->planner = $this->createMock(BreakpointSetPlanner::class);
        $this->retargeter = $this->createMock(CropAreaRetargeter::class);
        $this->retargeter->method('plan')->willReturnCallback(function (array $retargeted): CropRetargetPlan {
            $this->log[] = 'plan crop areas of ' . count($retargeted) . ' retargeted';

            return new CropRetargetPlan();
        });
        $this->retargeter->method('apply')->willReturnCallback(function (): void {
            $this->log[] = 'apply crop areas';
        });
        $this->retargeter->method('regenerate')->willReturnCallback(function (): array {
            $this->log[] = 'regenerate crops';

            return [];
        });
        $this->tagCleaner = $this->createMock(TagCleaner::class);
        $this->tagCleaner->method('clean')->willReturnCallback(function (array $tags): void {
            $this->log[] = 'clean ' . $this->joined($tags);
        });

        $this->editor = $this->editor();
    }

    /**
     * Update, rename, create, delete and the crop areas of retargeted breakpoints run in one transaction; crops are
     * rendered again and caches cleaned after the commit
     *
     * @return void
     */
    public function testSavesStoredSliderWithBreakpointChanges(): void
    {
        $slider = $this->slider(9, 'home-top');
        $inputs = [$this->input(1, 'desktop')];
        $desktop = $this->breakpoint(1, 'desktop');
        $renamed = $this->breakpoint(2, 'mobile');
        $created = $this->breakpoint(null, 'wide');
        $deleted = $this->breakpoint(3, 'tablet');
        $this->sliderRepository->method('getById')->with(9)->willReturnCallback(function (): SliderInterface {
            $this->log[] = 'load slider 9';

            return $this->slider(9, 'home');
        });
        $this->planner->expects(self::once())
            ->method('plan')
            ->with(9, $inputs)
            ->willReturnCallback(function () use ($desktop, $renamed, $created, $deleted): BreakpointSetPlan {
                $this->log[] = 'plan';

                return new BreakpointSetPlan([$desktop, $renamed, $created], [$deleted], [$renamed], [$desktop]);
            });
        $this->sliderRepository->method('save')->with($slider)->willReturnCallback(
            function (SliderInterface $saved): SliderInterface {
                $this->log[] = 'save slider';

                return $saved;
            }
        );

        self::assertSame($slider, $this->editor->save($slider, $inputs));
        self::assertSame(
            [
                'load slider 9',
                'plan',
                'plan crop areas of 1 retargeted',
                'beginTransaction',
                'save slider',
                'delete breakpoint 3',
                'save breakpoint 2 as renaming-2-' . substr(hash('sha256', 'mobile'), 0, 8) . ' of slider 9',
                'save breakpoint 1 as desktop of slider 9',
                'save breakpoint 2 as mobile of slider 9',
                'save breakpoint new as wide of slider 9',
                'apply crop areas',
                'commit',
                'regenerate crops',
                'clean hryvinskyi_banner_slider_9,hryvinskyi_banner_slider_location_home,'
                    . 'hryvinskyi_banner_slider_location_home_top',
            ],
            $this->log
        );
    }

    /**
     * A new slider gets its id first, and its breakpoints are saved under it
     *
     * @return void
     */
    public function testNewSliderBreakpointsGetTheNewId(): void
    {
        $slider = $this->slider(null, null);
        $saved = $this->slider(12, null);
        $created = $this->breakpoint(null, 'desktop');
        $this->sliderRepository->expects(self::never())->method('getById');
        $this->planner->method('plan')->with(null, [])->willReturn(new BreakpointSetPlan([$created], [], []));
        $this->sliderRepository->method('save')->willReturn($saved);

        self::assertSame($saved, $this->editor->save($slider, []));
        self::assertSame(12, $created->getSliderId());
        self::assertSame(
            [
                'plan crop areas of 0 retargeted',
                'beginTransaction',
                'save breakpoint new as desktop of slider 12',
                'apply crop areas',
                'commit',
                'regenerate crops',
                'clean hryvinskyi_banner_slider_12',
            ],
            $this->log
        );
    }

    /**
     * An invalid breakpoint set stops the save before any write
     *
     * @return void
     */
    public function testInvalidBreakpointsStopBeforeAnyWrite(): void
    {
        $this->sliderRepository->method('getById')->willReturn($this->slider(9, null));
        $this->planner->method('plan')->willThrowException(new ValidationException(__('Invalid.')));
        $this->sliderRepository->expects(self::never())->method('save');

        try {
            $this->editor->save($this->slider(9, null), [$this->input(44, 'desktop')]);
            self::fail('A validation exception was expected.');
        } catch (ValidationException) {
            self::assertSame([], $this->log);
        }
    }

    /**
     * A slider id nobody has is a validation error, before any write
     *
     * @return void
     */
    public function testUnknownSliderIdIsRejected(): void
    {
        $this->sliderRepository->method('getById')->willThrowException(new NoSuchEntityException(__('Missing.')));
        $this->planner->expects(self::never())->method('plan');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The slider with id "77" does not exist.');

        $this->editor->save($this->slider(77, null), []);
    }

    /**
     * A failing write rolls everything back; no file is removed and no cache cleaned
     *
     * @return void
     */
    public function testFailureRollsBack(): void
    {
        $failure = new CouldNotSaveException(__('Broken.'));
        $this->sliderRepository->method('getById')->willReturn($this->slider(9, null));
        $this->planner->method('plan')->willReturn(new BreakpointSetPlan(
            [$this->breakpoint(1, 'desktop')],
            [$this->breakpoint(3, 'tablet')],
            []
        ));
        $this->sliderRepository->method('save')->willThrowException($failure);

        try {
            $this->editor->save($this->slider(9, null), []);
            self::fail('The failure was expected to surface.');
        } catch (CouldNotSaveException $exception) {
            self::assertSame($failure, $exception);
        }
        self::assertSame(['plan crop areas of 0 retargeted', 'beginTransaction', 'rollBack'], $this->log);
    }

    /**
     * A breakpoint that cannot be deleted fails the slider save and rolls it back
     *
     * @return void
     */
    public function testDeleteFailureIsASaveFailure(): void
    {
        $slider = $this->slider(9, null);
        $this->sliderRepository->method('getById')->willReturn($this->slider(9, null));
        $this->sliderRepository->method('save')->willReturn($slider);
        $this->planner->method('plan')->willReturn(new BreakpointSetPlan([], [$this->breakpoint(3, 'tablet')], []));
        $this->breakpointRepository = $this->createMock(BreakpointRepositoryInterface::class);
        $this->breakpointRepository->method('delete')->willThrowException(new CouldNotDeleteException(__('No.')));

        $editor = $this->editor();

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('The breakpoint "tablet" could not be deleted.');

        $editor->save($slider, []);
    }

    /**
     * After a rollback a new slider has no id again, so saving it again inserts it; a stored slider keeps its id
     *
     * @return void
     */
    public function testRollbackForgetsTheIdOfANewSlider(): void
    {
        $this->planner->method('plan')->willReturn(new BreakpointSetPlan([$this->breakpoint(null, 'desktop')], [], []));
        $this->sliderRepository->method('getById')->willReturn($this->slider(9, null));
        $this->sliderRepository->method('save')->willReturnCallback(
            static function (SliderInterface $slider): SliderInterface {
                if ($slider->getSliderId() === null) {
                    $slider->setSliderId(12);
                }

                return $slider;
            }
        );
        $this->breakpointRepository = $this->createMock(BreakpointRepositoryInterface::class);
        $this->breakpointRepository->method('save')->willThrowException(new CouldNotSaveException(__('Broken.')));
        $editor = $this->editor();
        $new = $this->sliderModel();
        $stored = $this->sliderModel();
        $stored->setSliderId(9);

        foreach ([$new, $stored] as $slider) {
            try {
                $editor->save($slider, []);
                self::fail('The failure was expected to surface.');
            } catch (CouldNotSaveException) {
                continue;
            }
        }

        self::assertNull($new->getSliderId());
        self::assertSame(9, $stored->getSliderId());
    }

    /**
     * The retargeted breakpoints reach the crop area planning before the transaction; the tags of the re-rendered
     * banners are cleaned with the slider's
     *
     * @return void
     */
    public function testRetargetedCropsAreRenderedAgainAfterTheCommit(): void
    {
        $slider = $this->slider(9, null);
        $desktop = $this->breakpoint(1, 'desktop');
        $retargetPlan = new CropRetargetPlan([], [['bannerId' => 4, 'breakpointId' => 1]]);
        $this->sliderRepository->method('getById')->willReturn($this->slider(9, null));
        $this->sliderRepository->method('save')->willReturn($slider);
        $this->planner->method('plan')->willReturn(new BreakpointSetPlan([$desktop], [], [], [$desktop]));
        $retargeter = $this->createMock(CropAreaRetargeter::class);
        $retargeter->expects(self::once())->method('plan')->with([$desktop])->willReturn($retargetPlan);
        $retargeter->expects(self::once())->method('apply')->with($retargetPlan);
        $retargeter->expects(self::once())->method('regenerate')->with($retargetPlan)->willReturnCallback(
            function (): array {
                $this->log[] = 'regenerate crops';

                return ['hryvinskyi_banner_slider_banner_4'];
            }
        );
        $this->retargeter = $retargeter;

        $this->editor()->save($slider, []);

        self::assertSame(
            [
                'beginTransaction',
                'save breakpoint 1 as desktop of slider 9',
                'commit',
                'regenerate crops',
                'clean hryvinskyi_banner_slider_9,hryvinskyi_banner_slider_banner_4',
            ],
            $this->log
        );
    }

    /**
     * The editor over the current doubles
     *
     * @return SliderEditor
     */
    private function editor(): SliderEditor
    {
        return new SliderEditor(
            $this->resourceConnection,
            $this->sliderRepository,
            $this->breakpointRepository,
            $this->planner,
            $this->retargeter,
            $this->tagCleaner
        );
    }

    /**
     * A new slider model
     *
     * @return Slider
     */
    private function sliderModel(): Slider
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

    /**
     * A slider double
     *
     * @param int|null $id
     * @param string|null $location
     * @return SliderInterface&MockObject
     */
    private function slider(?int $id, ?string $location): SliderInterface&MockObject
    {
        $slider = $this->createMock(SliderInterface::class);
        $slider->method('getSliderId')->willReturn($id);
        $slider->method('getLocation')->willReturn($location);

        return $slider;
    }

    /**
     * A breakpoint model, stored when an id is given
     *
     * @param int|null $id
     * @param string $identifier
     * @return Breakpoint
     */
    private function breakpoint(?int $id, string $identifier): Breakpoint
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();
        $breakpoint = new Breakpoint(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(BreakpointInterface::BREAKPOINT_ID)
        );
        $breakpoint->setIdentifier($identifier);
        if ($id !== null) {
            $breakpoint->setBreakpointId($id)->setSliderId(9);
        }

        return $breakpoint;
    }

    /**
     * A desired breakpoint
     *
     * @param int|null $id
     * @param string $identifier
     * @return BreakpointInput
     */
    private function input(?int $id, string $identifier): BreakpointInput
    {
        return new BreakpointInput($id, ucfirst($identifier), $identifier, 'all', 0, 100, null, 0, true);
    }
}
