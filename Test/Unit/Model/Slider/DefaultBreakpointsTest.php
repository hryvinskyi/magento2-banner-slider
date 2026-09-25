<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Slider;

use Hryvinskyi\BannerSlider\Model\Slider\DefaultBreakpoints;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

#[CoversClass(DefaultBreakpoints::class)]
class DefaultBreakpointsTest extends TestCase
{
    /**
     * The shipped defaults are desktop, tablet and mobile with their established sizes
     *
     * @return void
     */
    public function testShippedDefaults(): void
    {
        $defaults = new DefaultBreakpoints($this->configuredBreakpoints());

        self::assertSame(
            [
                [null, 'Desktop', 'desktop', '(min-width: 1200px)', 1200, 1920, 600, 10, true],
                [null, 'Tablet', 'tablet', '(min-width: 768px) and (max-width: 1199px)', 768, 992, 400, 30, true],
                [null, 'Mobile', 'mobile', '(max-width: 767px)', 0, 767, 500, 40, true],
            ],
            array_map(fn (BreakpointInput $input): array => $this->describe($input), $defaults->get())
        );
    }

    /**
     * Optional fields fall back: no target height keeps the aspect ratio, sort order 0, enabled
     *
     * @return void
     */
    public function testOptionalFields(): void
    {
        $defaults = new DefaultBreakpoints([
            'wide' => [
                'name' => 'Wide',
                'identifier' => 'wide',
                'media_query' => '(min-width: 1600px)',
                'min_width' => 1600,
                'target_width' => '2560',
                'target_height' => '',
            ],
            'off' => [
                'name' => 'Off',
                'identifier' => 'off',
                'media_query' => 'all',
                'min_width' => '0',
                'target_width' => '100',
                'enabled' => 'false',
            ],
        ]);

        self::assertSame(
            [
                [null, 'Wide', 'wide', '(min-width: 1600px)', 1600, 2560, null, 0, true],
                [null, 'Off', 'off', 'all', 0, 100, null, 0, false],
            ],
            array_map(fn (BreakpointInput $input): array => $this->describe($input), $defaults->get())
        );
    }

    /**
     * A broken definition fails when the object is built, naming the item
     *
     * @param mixed $definition
     * @param string $message
     * @return void
     */
    #[TestWith(['not an array', 'must be an array'])]
    #[TestWith([['identifier' => 'x', 'media_query' => 'all', 'min_width' => 0, 'target_width' => 1], '"name"'])]
    #[TestWith([['name' => 'X', 'identifier' => 'x', 'media_query' => 'all', 'min_width' => 'wide',
        'target_width' => 1], '"min_width"'])]
    #[TestWith([['name' => 'X', 'identifier' => 'Bad Id', 'media_query' => 'all', 'min_width' => 0,
        'target_width' => 1], 'identifier'])]
    public function testRejectsBrokenDefinitions(mixed $definition, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"broken".*' . preg_quote($message, '/') . '/');

        new DefaultBreakpoints(['broken' => $definition]);
    }

    /**
     * The values of a breakpoint input as a list
     *
     * @param BreakpointInput $input
     * @return list<mixed>
     */
    private function describe(BreakpointInput $input): array
    {
        return [
            $input->getBreakpointId(),
            $input->getName(),
            $input->getIdentifier(),
            $input->getMediaQuery(),
            $input->getMinWidth(),
            $input->getTargetWidth(),
            $input->getTargetHeight(),
            $input->getSortOrder(),
            $input->isEnabled(),
        ];
    }

    /**
     * The `breakpoints` argument of the class in `etc/di.xml`, as the object manager passes it (numbers as strings)
     *
     * @return array<mixed>
     */
    private function configuredBreakpoints(): array
    {
        $config = simplexml_load_file(dirname(__DIR__, 4) . '/etc/di.xml');
        self::assertNotFalse($config);
        $arguments = $config->xpath(
            '//type[@name="' . DefaultBreakpoints::class . '"]/arguments/argument[@name="breakpoints"]'
        );
        self::assertIsArray($arguments);
        self::assertCount(1, $arguments);

        return $this->items($arguments[0]);
    }

    /**
     * The items of an array argument, recursively
     *
     * @param SimpleXMLElement $node
     * @return array<string, mixed>
     */
    private function items(SimpleXMLElement $node): array
    {
        $items = [];
        foreach ($node->item as $item) {
            $name = (string)$item['name'];
            $type = (string)$item->attributes('xsi', true)['type'];
            $items[$name] = $type === 'array' ? $this->items($item) : (string)$item;
        }

        return $items;
    }
}
