<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Data\ResponsiveItemsCodec;
use Hryvinskyi\BannerSlider\Model\Migration\LegacyResponsiveItemsConverter;
use Hryvinskyi\BannerSlider\Model\Migration\MaxWidthBreakpointTranslator;
use Hryvinskyi\BannerSlider\Model\Migration\ResponsiveItemsConversion;
use Hryvinskyi\BannerSlider\Model\Migration\ResponsiveLayoutComparator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyResponsiveItemsConverter::class)]
#[CoversClass(ResponsiveItemsConversion::class)]
class LegacyResponsiveItemsConverterTest extends TestCase
{
    /**
     * @var LegacyResponsiveItemsConverter
     */
    private LegacyResponsiveItemsConverter $converter;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->converter = new LegacyResponsiveItemsConverter(
            new ResponsiveItemsCodec(),
            new MaxWidthBreakpointTranslator(),
            new ResponsiveLayoutComparator()
        );
    }

    /**
     * One slide per page at every width stays one slide per page, and is not reported as a changed layout
     *
     * @param string $stored
     * @return void
     */
    #[TestWith(['{"0":{"items":1},"768":{"items":1},"1024":{"items":1}}'])]
    #[TestWith(['{"1024":{"items":1},"0":{"items":1}}'])]
    #[TestWith(['{"768":{"items":"1"}}'])]
    public function testOneSlideEverywhereStaysOneSlide(string $stored): void
    {
        $result = $this->converter->convert($stored);

        self::assertTrue($result->isChanged());
        self::assertSame('[{"min_width":0,"per_page":1,"gap":null}]', $result->getJson());
        self::assertFalse($result->isReinterpreted());
        self::assertSame([], $result->getNotes());
    }

    /**
     * Legacy widths were maximum widths: each becomes the range up to it, and above the widest the base applies
     *
     * @param string $stored
     * @param string $expected
     * @return void
     */
    #[TestWith([
        '{"0":{"items":1},"768":{"items":3}}',
        '[{"min_width":0,"per_page":3,"gap":null},{"min_width":769,"per_page":1,"gap":null}]',
    ])]
    #[TestWith([
        '{"0":{"items":1},"600":{"items":2},"1000":{"items":3}}',
        '[{"min_width":0,"per_page":2,"gap":null},{"min_width":601,"per_page":3,"gap":null},'
            . '{"min_width":1001,"per_page":1,"gap":null}]',
    ])]
    #[TestWith([
        '{"1024":{"items":4,"gap":"1.5rem"},"0":{"items":2,"gap":"12px"}}',
        '[{"min_width":0,"per_page":4,"gap":"1.5rem"},{"min_width":1025,"per_page":1,"gap":"0px"}]',
    ])]
    #[TestWith([
        '{"600":{"items":2},"1000":{"items":3,"gap":"10px"}}',
        '[{"min_width":0,"per_page":2,"gap":"10px"},{"min_width":601,"per_page":3,"gap":"10px"},'
            . '{"min_width":1001,"per_page":1,"gap":"0px"}]',
    ])]
    #[TestWith(['{"0":{"items":2}}', '[]'])]
    #[TestWith([
        ' {"480":{"items":"3","gap":null}} ',
        '[{"min_width":0,"per_page":3,"gap":null},{"min_width":481,"per_page":1,"gap":null}]',
    ])]
    public function testMaxWidthsBecomeMinWidthRanges(string $stored, string $expected): void
    {
        $result = $this->converter->convert($stored);

        self::assertTrue($result->isChanged());
        self::assertSame($expected, $result->getJson());
        self::assertTrue($result->isReinterpreted());
        self::assertCount(1, $result->getNotes());
        self::assertStringContainsString($expected, $result->getNotes()[0]);
    }

    /**
     * An empty object has no rules and nothing to report
     *
     * @return void
     */
    public function testEmptyObjectBecomesEmptyList(): void
    {
        $result = $this->converter->convert('{}');

        self::assertSame('[]', $result->getJson());
        self::assertFalse($result->isReinterpreted());
        self::assertSame([], $result->getNotes());
    }

    /**
     * A bare number as gap is read as pixels and noted
     *
     * @param string $stored
     * @param string $expectedGap
     * @return void
     */
    #[TestWith(['{"100":{"items":1,"gap":10}}', '10px'])]
    #[TestWith(['{"100":{"items":1,"gap":"8"}}', '8px'])]
    #[TestWith(['{"100":{"items":1,"gap":2.5}}', '2.5px'])]
    public function testNumericGapBecomesPixels(string $stored, string $expectedGap): void
    {
        $result = $this->converter->convert($stored);

        self::assertSame(
            sprintf(
                '[{"min_width":0,"per_page":1,"gap":"%s"},{"min_width":101,"per_page":1,"gap":"0px"}]',
                $expectedGap
            ),
            $result->getJson()
        );
        self::assertStringStartsWith('Gap ', $result->getNotes()[0]);
    }

    /**
     * An invalid gap is treated as not set and noted
     *
     * @param string $stored
     * @return void
     */
    #[TestWith(['{"100":{"items":2,"gap":"wide"}}'])]
    #[TestWith(['{"100":{"items":2,"gap":-4}}'])]
    #[TestWith(['{"100":{"items":2,"gap":"-4"}}'])]
    #[TestWith(['{"100":{"items":2,"gap":true}}'])]
    #[TestWith(['{"100":{"items":2,"gap":{"x":1}}}'])]
    public function testInvalidGapIsDropped(string $stored): void
    {
        $result = $this->converter->convert($stored);

        self::assertSame(
            '[{"min_width":0,"per_page":2,"gap":null},{"min_width":101,"per_page":1,"gap":null}]',
            $result->getJson()
        );
        self::assertSame('Dropped the gap at width 100: it is not a CSS length.', $result->getNotes()[0]);
    }

    /**
     * Slides per page outside 1..10 are clamped; slides per page that are not a number are not set
     *
     * @param string $stored
     * @param int $expectedPerPage
     * @param string $expectedNote
     * @return void
     */
    #[TestWith(['{"100":{"items":0}}', 1, 'Slides per page at width 100 was 0; clamped to 1.'])]
    #[TestWith(['{"100":{"items":-3}}', 1, 'Slides per page at width 100 was -3; clamped to 1.'])]
    #[TestWith(['{"100":{"items":25}}', 10, 'Slides per page at width 100 was 25; clamped to 10.'])]
    #[TestWith(['{"100":{"items":"many"}}', 1, 'Slides per page at width 100 is not a number; ignored.'])]
    public function testPerPageIsClamped(string $stored, int $expectedPerPage, string $expectedNote): void
    {
        $result = $this->converter->convert($stored);
        $decoded = json_decode($result->getJson(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertIsArray($decoded[0]);
        self::assertSame($expectedPerPage, $decoded[0]['per_page']);
        self::assertSame($expectedNote, $result->getNotes()[0]);
    }

    /**
     * Settings without a list-shape equivalent are dropped, one note each
     *
     * @return void
     */
    public function testLegacyOnlyKeysAreDropped(): void
    {
        $result = $this->converter->convert('{"768":{"items":2,"nav":true,"dots":false,"autoplay":true}}');

        self::assertSame(
            '[{"min_width":0,"per_page":2,"gap":null},{"min_width":769,"per_page":1,"gap":null}]',
            $result->getJson()
        );
        self::assertCount(4, $result->getNotes(), 'Three dropped settings and the changed layout.');
    }

    /**
     * Entries whose key is not a width or whose settings are not an object are dropped and noted
     *
     * @return void
     */
    public function testUnusableEntriesAreDropped(): void
    {
        $result = $this->converter->convert('{"mobile":{"items":1},"-5":{"items":1},"480":3,"0":{"items":1}}');

        self::assertSame('[]', $result->getJson());
        self::assertFalse($result->isReinterpreted());
        self::assertCount(3, $result->getNotes());
    }

    /**
     * A value that is not JSON, or JSON that is neither object nor list, becomes an empty list
     *
     * @param string $stored
     * @return void
     */
    #[TestWith(['{"0":{"items":1}'])]
    #[TestWith(['not json'])]
    #[TestWith(['   '])]
    #[TestWith(['5'])]
    #[TestWith(['"text"'])]
    #[TestWith(['null'])]
    public function testUnusableValueBecomesEmptyList(string $stored): void
    {
        $result = $this->converter->convert($stored);

        self::assertTrue($result->isChanged());
        self::assertSame('[]', $result->getJson());
        self::assertCount(1, $result->getNotes());
    }

    /**
     * A value already in list shape is left as stored
     *
     * @param string $stored
     * @return void
     */
    #[TestWith(['[{"min_width":0,"per_page":1,"gap":null}]'])]
    #[TestWith(['[]'])]
    public function testListShapeIsLeftAlone(string $stored): void
    {
        $result = $this->converter->convert($stored);

        self::assertFalse($result->isChanged());
        self::assertFalse($result->isReinterpreted());
        self::assertSame($stored, $result->getJson());
        self::assertSame([], $result->getNotes());
    }
}
