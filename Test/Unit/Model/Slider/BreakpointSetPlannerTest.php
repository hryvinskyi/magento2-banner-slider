<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Slider;

use Hryvinskyi\BannerSlider\Model\Breakpoint;
use Hryvinskyi\BannerSlider\Model\Slider\BreakpointSetPlan;
use Hryvinskyi\BannerSlider\Model\Slider\BreakpointSetPlanner;
use Hryvinskyi\BannerSlider\Model\Slider\DefaultBreakpoints;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointInput;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(BreakpointSetPlanner::class)]
#[CoversClass(BreakpointSetPlan::class)]
class BreakpointSetPlannerTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var BreakpointRepositoryInterface&MockObject
     */
    private MockObject $repository;

    /**
     * @var BreakpointInterfaceFactory&MockObject
     */
    private MockObject $factory;

    /**
     * @var BreakpointSetPlanner
     */
    private BreakpointSetPlanner $planner;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(BreakpointRepositoryInterface::class);
        $this->factory = $this->createMock(BreakpointInterfaceFactory::class);
        $this->factory->method('create')->willReturnCallback(fn (): Breakpoint => $this->breakpoint());
        $this->planner = new BreakpointSetPlanner(
            $this->repository,
            $this->factory,
            new DefaultBreakpoints([
                'desktop' => $this->definition('desktop', 1200),
                'mobile' => $this->definition('mobile', 0),
            ])
        );
    }

    /**
     * A new slider given no breakpoints gets the defaults, and nothing is read
     *
     * @return void
     */
    public function testNewSliderWithoutInputsGetsDefaults(): void
    {
        $this->repository->expects(self::never())->method('getBySliderId');

        $plan = $this->planner->plan(null, []);

        self::assertSame(['desktop', 'mobile'], $this->identifiers($plan->getToSave()));
        self::assertSame([1200, 0], array_map(
            fn (BreakpointInterface $breakpoint): int => $breakpoint->getMinWidth(),
            $plan->getToSave()
        ));
        self::assertSame([], $plan->getToDelete());
        self::assertSame([], $plan->getRenamed());
    }

    /**
     * Stored breakpoints are updated, renamed or deleted, inputs without id are created
     *
     * @return void
     */
    public function testDiffAgainstStoredBreakpoints(): void
    {
        $desktop = $this->breakpoint(1, 'desktop');
        $tablet = $this->breakpoint(2, 'tablet');
        $mobile = $this->breakpoint(3, 'mobile');
        $this->repository->expects(self::once())
            ->method('getBySliderId')
            ->with(9)
            ->willReturn([$desktop, $tablet, $mobile]);

        $plan = $this->planner->plan(9, [
            $this->input(1, 'desktop', 'Large screens'),
            $this->input(2, 'mobile'),
            $this->input(null, 'wide'),
        ]);

        $toSave = $plan->getToSave();
        self::assertCount(3, $toSave);
        self::assertSame($desktop, $toSave[0]);
        self::assertSame('Large screens', $desktop->getName());
        self::assertSame($tablet, $toSave[1]);
        self::assertSame('mobile', $tablet->getIdentifier());
        self::assertNull($toSave[2]->getBreakpointId());
        self::assertSame('wide', $toSave[2]->getIdentifier());
        self::assertSame([$mobile], $plan->getToDelete());
        self::assertSame([3], $plan->getIdsToDelete());
        self::assertSame([$tablet], $plan->getRenamed());
        self::assertSame([], $plan->getRetargeted());
    }

    /**
     * A stored breakpoint whose target width or height changes is retargeted, carrying the new target; one whose
     * target stays the same, and a new one, are not
     *
     * @return void
     */
    public function testTargetChangeIsReported(): void
    {
        $wider = $this->breakpoint(1, 'desktop');
        $taller = $this->breakpoint(2, 'tablet');
        $taller->setTargetHeight(300);
        $same = $this->breakpoint(3, 'mobile');
        $same->setTargetHeight(500);
        $this->repository->method('getBySliderId')->willReturn([$wider, $taller, $same]);

        $plan = $this->planner->plan(9, [
            $this->input(1, 'desktop', null, 1920, null),
            $this->input(2, 'tablet', null, 100, 400),
            $this->input(3, 'mobile', null, 100, 500),
            $this->input(null, 'wide', null, 2560, 800),
        ]);

        self::assertSame([$wider, $taller], $plan->getRetargeted());
        self::assertSame(1920, $wider->getTargetWidth());
        self::assertSame(400, $taller->getTargetHeight());
    }

    /**
     * Two stored breakpoints may swap identifiers; both count as renamed
     *
     * @return void
     */
    public function testIdentifierSwapIsAllowed(): void
    {
        $desktop = $this->breakpoint(1, 'desktop');
        $mobile = $this->breakpoint(2, 'mobile');
        $this->repository->method('getBySliderId')->willReturn([$desktop, $mobile]);

        $plan = $this->planner->plan(9, [$this->input(1, 'mobile'), $this->input(2, 'desktop')]);

        self::assertSame([$desktop, $mobile], $plan->getRenamed());
        self::assertSame([], $plan->getToDelete());
    }

    /**
     * An existing slider saved without breakpoints loses them all; defaults are for new sliders only
     *
     * @return void
     */
    public function testExistingSliderWithoutInputsDeletesAll(): void
    {
        $desktop = $this->breakpoint(1, 'desktop');
        $this->repository->method('getBySliderId')->willReturn([$desktop]);

        $plan = $this->planner->plan(9, []);

        self::assertSame([], $plan->getToSave());
        self::assertSame([$desktop], $plan->getToDelete());
    }

    /**
     * Foreign and repeated ids and repeated identifiers are all reported at once, before any breakpoint is built
     *
     * @return void
     */
    public function testInvalidSetIsRejectedBeforeAnyChange(): void
    {
        $desktop = $this->breakpoint(1, 'desktop');
        $this->repository->method('getBySliderId')->willReturn([$desktop]);
        $this->factory->expects(self::never())->method('create');

        try {
            $this->planner->plan(9, [
                $this->input(1, 'desktop', 'Changed'),
                $this->input(1, 'tablet'),
                $this->input(44, 'mobile'),
                $this->input(null, 'mobile'),
            ]);
            self::fail('A validation exception was expected.');
        } catch (ValidationException $exception) {
            self::assertSame(
                [
                    'The breakpoint with id "1" is listed more than once.',
                    'The breakpoint with id "44" does not belong to this slider.',
                    'More than one breakpoint uses the identifier "mobile".',
                ],
                array_map(fn (LocalizedException $error): string => $error->getMessage(), $exception->getErrors())
            );
        }
        self::assertSame('Desktop', $desktop->getName());
    }

    /**
     * A new slider cannot claim stored breakpoints
     *
     * @return void
     */
    public function testNewSliderCannotReferenceBreakpointIds(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not belong to this slider');

        $this->planner->plan(null, [$this->input(5, 'desktop')]);
    }

    /**
     * A value the breakpoint refuses becomes a validation error naming the breakpoint
     *
     * @return void
     */
    public function testRefusedValueIsAValidationError(): void
    {
        $this->repository->method('getBySliderId')->willReturn([]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Breakpoint "evil"');

        $this->planner->plan(9, [
            new BreakpointInput(null, 'Evil', 'evil', '(min-width: 1px)</style>', 1, 100, null, 0, true),
        ]);
    }

    /**
     * A breakpoint model, stored when an id is given
     *
     * @param int|null $id
     * @param string $identifier
     * @return Breakpoint
     */
    private function breakpoint(?int $id = null, string $identifier = ''): Breakpoint
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();
        $breakpoint = new Breakpoint(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(BreakpointInterface::BREAKPOINT_ID)
        );
        if ($id !== null) {
            $breakpoint->setBreakpointId($id)
                ->setSliderId(9)
                ->setName(ucfirst($identifier))
                ->setIdentifier($identifier)
                ->setMediaQuery('all')
                ->setTargetWidth(100);
        }

        return $breakpoint;
    }

    /**
     * A desired breakpoint
     *
     * @param int|null $id
     * @param string $identifier
     * @param string|null $name
     * @param int $targetWidth
     * @param int|null $targetHeight
     * @return BreakpointInput
     */
    private function input(
        ?int $id,
        string $identifier,
        ?string $name = null,
        int $targetWidth = 100,
        ?int $targetHeight = null
    ): BreakpointInput {
        return new BreakpointInput(
            $id,
            $name ?? ucfirst($identifier),
            $identifier,
            '(min-width: 1px)',
            1,
            $targetWidth,
            $targetHeight,
            0,
            true
        );
    }

    /**
     * A default breakpoint definition as `di.xml` passes it
     *
     * @param string $identifier
     * @param int $minWidth
     * @return array<string, string>
     */
    private function definition(string $identifier, int $minWidth): array
    {
        return [
            'name' => ucfirst($identifier),
            'identifier' => $identifier,
            'media_query' => '(min-width: ' . $minWidth . 'px)',
            'min_width' => (string)$minWidth,
            'target_width' => '800',
            'target_height' => '400',
        ];
    }

    /**
     * Identifiers of breakpoints
     *
     * @param list<BreakpointInterface> $breakpoints
     * @return list<string>
     */
    private function identifiers(array $breakpoints): array
    {
        return array_map(fn (BreakpointInterface $breakpoint): string => $breakpoint->getIdentifier(), $breakpoints);
    }
}
