<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider;

use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointInput;

/**
 * The breakpoints a new slider starts with when it is saved without any, configured in `di.xml`.
 *
 * Each item holds `name`, `identifier`, `media_query`, `min_width`, `target_width`, optional `target_height` (empty
 * or missing keeps the crop's aspect ratio), optional `sort_order` and optional `enabled` (default on). The items are
 * turned into breakpoint inputs when this object is built, so a broken item fails at once, naming the item, instead
 * of when the first slider is saved.
 */
class DefaultBreakpoints
{
    /**
     * @var list<BreakpointInput>
     */
    private readonly array $breakpoints;

    /**
     * @param array<mixed> $breakpoints Breakpoint definitions keyed by identifier
     * @throws \InvalidArgumentException When an item is not a valid breakpoint definition
     */
    public function __construct(array $breakpoints = [])
    {
        $inputs = [];
        foreach ($breakpoints as $key => $definition) {
            if (!is_array($definition)) {
                throw new \InvalidArgumentException(
                    sprintf('The default breakpoint "%s" must be an array of breakpoint fields.', $key)
                );
            }
            try {
                $inputs[] = $this->toInput($definition);
            } catch (\InvalidArgumentException $exception) {
                throw new \InvalidArgumentException(
                    sprintf('The default breakpoint "%s" is not valid: %s', $key, $exception->getMessage()),
                    0,
                    $exception
                );
            }
        }
        $this->breakpoints = $inputs;
    }

    /**
     * The default breakpoints, as new breakpoint inputs
     *
     * @return list<BreakpointInput>
     */
    public function get(): array
    {
        return $this->breakpoints;
    }

    /**
     * Build a new breakpoint input from a definition
     *
     * @param array<mixed> $definition
     * @return BreakpointInput
     * @throws \InvalidArgumentException When a field is missing or invalid
     */
    private function toInput(array $definition): BreakpointInput
    {
        $targetHeight = $definition['target_height'] ?? null;

        return new BreakpointInput(
            null,
            $this->string($definition, 'name'),
            $this->string($definition, 'identifier'),
            $this->string($definition, 'media_query'),
            $this->integer($definition, 'min_width'),
            $this->integer($definition, 'target_width'),
            $targetHeight === null || $targetHeight === '' ? null : $this->integer($definition, 'target_height'),
            isset($definition['sort_order']) ? $this->integer($definition, 'sort_order') : 0,
            !isset($definition['enabled']) || filter_var($definition['enabled'], FILTER_VALIDATE_BOOLEAN)
        );
    }

    /**
     * A required text field
     *
     * @param array<mixed> $definition
     * @param string $field
     * @return string
     * @throws \InvalidArgumentException When the field is missing or not text
     */
    private function string(array $definition, string $field): string
    {
        $value = $definition[$field] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('The field "%s" must be text.', $field));
        }

        return $value;
    }

    /**
     * A required whole-number field; `di.xml` numbers arrive as numeric strings
     *
     * @param array<mixed> $definition
     * @param string $field
     * @return int
     * @throws \InvalidArgumentException When the field is missing or not a whole number
     */
    private function integer(array $definition, string $field): int
    {
        $value = $definition[$field] ?? null;
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($integer)) {
            throw new \InvalidArgumentException(sprintf('The field "%s" must be a whole number.', $field));
        }

        return $integer;
    }
}
