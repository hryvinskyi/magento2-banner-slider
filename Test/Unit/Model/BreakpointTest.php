<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model;

use Hryvinskyi\BannerSlider\Model\Breakpoint;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Breakpoint::class)]
class BreakpointTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var Breakpoint
     */
    private Breakpoint $breakpoint;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();
        $this->breakpoint = new Breakpoint(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(BreakpointInterface::BREAKPOINT_ID)
        );
    }

    /**
     * Valid values pass the setters and form the rendering spec
     *
     * @return void
     */
    public function testSettersAndSpec(): void
    {
        $this->breakpoint->setBreakpointId(7)
            ->setSliderId(2)
            ->setName('Desktop')
            ->setIdentifier('desktop')
            ->setMediaQuery('(min-width: 1200px)')
            ->setMinWidth(1200)
            ->setTargetWidth(1920)
            ->setTargetHeight(600)
            ->setSortOrder(10)
            ->setIsEnabled(false);

        self::assertSame(7, $this->breakpoint->getBreakpointId());
        self::assertSame(2, $this->breakpoint->getSliderId());
        self::assertSame('Desktop', $this->breakpoint->getName());
        self::assertSame(10, $this->breakpoint->getSortOrder());
        self::assertFalse($this->breakpoint->isEnabled());
        $spec = $this->breakpoint->toSpec();
        self::assertSame('desktop', $spec->getIdentifier());
        self::assertSame('(min-width: 1200px)', $spec->getMediaQuery());
        self::assertSame(1200, $spec->getMinWidth());
        self::assertSame(1920, $spec->getWidth());
        self::assertSame(600, $spec->getHeight());

        $this->breakpoint->setTargetHeight(null);
        self::assertNull($this->breakpoint->getTargetHeight());
    }

    /**
     * A stored target height of zero reads as "keep the aspect ratio"
     *
     * @return void
     */
    public function testZeroStoredTargetHeightReadsAsNull(): void
    {
        $this->breakpoint->setData(BreakpointInterface::TARGET_HEIGHT, '0');

        self::assertNull($this->breakpoint->getTargetHeight());
    }

    /**
     * A stored row that breaks the spec rules still produces a spec
     *
     * @return void
     */
    public function testSpecFromIncompleteStoredRow(): void
    {
        $this->breakpoint->setData([
            BreakpointInterface::BREAKPOINT_ID => '9',
            BreakpointInterface::IDENTIFIER => '',
            BreakpointInterface::TARGET_WIDTH => '0',
        ]);

        $spec = $this->breakpoint->toSpec();

        self::assertSame('breakpoint-9', $spec->getIdentifier());
        self::assertSame(1, $spec->getWidth());
        self::assertNull($spec->getHeight());
    }

    /**
     * Identifiers follow the slug rule
     *
     * @param string $identifier
     * @param bool $valid
     * @return void
     */
    #[TestWith(['mobile', true])]
    #[TestWith(['tablet_2-wide', true])]
    #[TestWith(['9col', true])]
    #[TestWith(['Desktop', false])]
    #[TestWith(['-mobile', false])]
    #[TestWith(['../x', false])]
    #[TestWith(['', false])]
    #[TestWith(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', false])]
    public function testIdentifierRule(string $identifier, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(\InvalidArgumentException::class);
        }

        $this->breakpoint->setIdentifier($identifier);

        self::assertSame($identifier, $this->breakpoint->getIdentifier());
    }

    /**
     * Each setter rejects a value that breaks its field's rule
     *
     * @param string $setter
     * @param int|string $value
     * @return void
     */
    #[TestWith(['setBreakpointId', 0])]
    #[TestWith(['setSliderId', 0])]
    #[TestWith(['setName', ' '])]
    #[TestWith(['setMediaQuery', ''])]
    #[TestWith(['setMediaQuery', '(min-width: 1px) { body'])]
    #[TestWith(['setMediaQuery', 'screen}'])]
    #[TestWith(['setMediaQuery', '</style>'])]
    #[TestWith(['setMinWidth', -1])]
    #[TestWith(['setTargetWidth', 0])]
    #[TestWith(['setTargetHeight', 0])]
    #[TestWith(['setTargetWidth', 5001])]
    #[TestWith(['setTargetHeight', 5001])]
    public function testSetterGuards(string $setter, int|string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $callable = [$this->breakpoint, $setter];
        self::assertIsCallable($callable);
        $callable($value);
    }

    /**
     * A target of exactly 5000 pixels is accepted, and a larger one is refused with a message naming the limit
     *
     * @return void
     */
    public function testTargetSizeLimit(): void
    {
        $this->breakpoint->setTargetWidth(5000)->setTargetHeight(5000);
        self::assertSame([5000, 5000], [$this->breakpoint->getTargetWidth(), $this->breakpoint->getTargetHeight()]);

        $this->expectExceptionMessage('Breakpoint target width must be at most 5000 pixels, got 5001.');
        $this->breakpoint->setTargetWidth(5001);
    }

    /**
     * Identities hold the breakpoint tag and its slider's tag
     *
     * @return void
     */
    public function testIdentities(): void
    {
        $this->breakpoint->setBreakpointId(7)->setSliderId(2);

        self::assertSame(
            [Breakpoint::CACHE_TAG . '_7', SliderInterface::CACHE_TAG . '_2'],
            $this->breakpoint->getIdentities()
        );
    }
}
