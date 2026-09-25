<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider;

use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointInput;
use Magento\Framework\Phrase;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;

/**
 * Compares a slider's desired breakpoints with the stored ones and works out what to save and delete, without
 * writing anything.
 *
 * - A new slider given no breakpoints gets the default set.
 * - An input with an id updates that breakpoint, which must belong to the slider; an input without one creates a
 *   breakpoint; a stored breakpoint missing from the inputs is deleted.
 * - Identifiers must be unique within the desired set, and an id may appear once. Uniqueness is checked on the set
 *   as a whole, so two breakpoints may swap identifiers.
 * - An existing breakpoint whose target width or height changes is reported as retargeted.
 *
 * Every broken rule is reported at once in one validation exception, before any write.
 */
class BreakpointSetPlanner
{
    /**
     * @param BreakpointRepositoryInterface $breakpointRepository
     * @param BreakpointInterfaceFactory $breakpointFactory
     * @param DefaultBreakpoints $defaultBreakpoints
     */
    public function __construct(
        private readonly BreakpointRepositoryInterface $breakpointRepository,
        private readonly BreakpointInterfaceFactory $breakpointFactory,
        private readonly DefaultBreakpoints $defaultBreakpoints
    ) {
    }

    /**
     * Plan the breakpoint changes of a slider
     *
     * @param int|null $sliderId Id of the stored slider, or null for a new one
     * @param list<BreakpointInput> $inputs The full desired set
     * @return BreakpointSetPlan
     * @throws ValidationException When an input breaks a rule
     */
    public function plan(?int $sliderId, array $inputs): BreakpointSetPlan
    {
        if ($sliderId === null && $inputs === []) {
            $inputs = $this->defaultBreakpoints->get();
        }
        $existing = [];
        if ($sliderId !== null) {
            foreach ($this->breakpointRepository->getBySliderId($sliderId) as $breakpoint) {
                $id = $breakpoint->getBreakpointId();
                if ($id !== null) {
                    $existing[$id] = $breakpoint;
                }
            }
        }

        $this->assertValidSet($inputs, $existing);

        $errors = [];
        $toSave = [];
        $renamed = [];
        $retargeted = [];
        $kept = [];
        foreach ($inputs as $input) {
            $id = $input->getBreakpointId();
            $breakpoint = $id === null ? $this->breakpointFactory->create() : $existing[$id];
            $previousIdentifier = $id === null ? null : $breakpoint->getIdentifier();
            $previousTarget = [$breakpoint->getTargetWidth(), $breakpoint->getTargetHeight()];
            try {
                $this->apply($input, $breakpoint);
            } catch (\InvalidArgumentException $exception) {
                $errors[] = __('Breakpoint "%1": %2', $input->getIdentifier(), $exception->getMessage());
                continue;
            }
            $toSave[] = $breakpoint;
            if ($id !== null) {
                $kept[$id] = true;
                if ($previousIdentifier !== $input->getIdentifier()) {
                    $renamed[] = $breakpoint;
                }
                if ($previousTarget !== [$input->getTargetWidth(), $input->getTargetHeight()]) {
                    $retargeted[] = $breakpoint;
                }
            }
        }
        $this->throwIfAny($errors);

        return new BreakpointSetPlan(
            $toSave,
            array_values(array_diff_key($existing, $kept)),
            $renamed,
            $retargeted
        );
    }

    /**
     * Check ids and identifiers across the whole desired set
     *
     * @param list<BreakpointInput> $inputs
     * @param array<int,BreakpointInterface> $existing Stored breakpoints of the slider by id
     * @return void
     * @throws ValidationException When an id is foreign or repeated, or an identifier is repeated
     */
    private function assertValidSet(array $inputs, array $existing): void
    {
        $errors = [];
        $ids = [];
        $identifiers = [];
        foreach ($inputs as $input) {
            $id = $input->getBreakpointId();
            if ($id !== null && !isset($existing[$id])) {
                $errors[] = __('The breakpoint with id "%1" does not belong to this slider.', $id);
            }
            if ($id !== null && isset($ids[$id])) {
                $errors[] = __('The breakpoint with id "%1" is listed more than once.', $id);
            }
            if ($id !== null) {
                $ids[$id] = true;
            }
            $identifier = $input->getIdentifier();
            if (isset($identifiers[$identifier])) {
                $errors[] = __('More than one breakpoint uses the identifier "%1".', $identifier);
            }
            $identifiers[$identifier] = true;
        }
        $this->throwIfAny($errors);
    }

    /**
     * Copy the desired values onto a breakpoint
     *
     * @param BreakpointInput $input
     * @param BreakpointInterface $breakpoint
     * @return void
     * @throws \InvalidArgumentException When the breakpoint refuses a value
     */
    private function apply(BreakpointInput $input, BreakpointInterface $breakpoint): void
    {
        $breakpoint->setName($input->getName())
            ->setIdentifier($input->getIdentifier())
            ->setMediaQuery($input->getMediaQuery())
            ->setMinWidth($input->getMinWidth())
            ->setTargetWidth($input->getTargetWidth())
            ->setTargetHeight($input->getTargetHeight())
            ->setSortOrder($input->getSortOrder())
            ->setIsEnabled($input->isEnabled());
    }

    /**
     * Throw one validation exception carrying every error, if there is any
     *
     * @param list<Phrase> $errors
     * @return void
     * @throws ValidationException
     */
    private function throwIfAny(array $errors): void
    {
        if ($errors === []) {
            return;
        }

        $messages = array_map(fn (Phrase $error): string => $error->render(), $errors);
        throw new ValidationException(
            __('The slider breakpoints are not valid: %1', implode(' ', $messages)),
            null,
            0,
            new ValidationResult($errors)
        );
    }
}
