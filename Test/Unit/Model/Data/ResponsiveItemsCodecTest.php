<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Data;

use Hryvinskyi\BannerSlider\Model\Data\ResponsiveItemsCodec;
use Hryvinskyi\BannerSliderApi\Api\Value\ResponsiveItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponsiveItemsCodec::class)]
class ResponsiveItemsCodecTest extends TestCase
{
    /**
     * @var ResponsiveItemsCodec
     */
    private ResponsiveItemsCodec $codec;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->codec = new ResponsiveItemsCodec();
    }

    /**
     * Items are encoded ascending by min width in the list shape
     *
     * @return void
     */
    public function testEncodeSortsAscending(): void
    {
        $json = $this->codec->encode([new ResponsiveItem(1024, 4, '1.5rem'), new ResponsiveItem(0, 1, null)]);

        self::assertSame(
            '[{"min_width":0,"per_page":1,"gap":null},{"min_width":1024,"per_page":4,"gap":"1.5rem"}]',
            $json
        );
    }

    /**
     * No items encode to an empty list
     *
     * @return void
     */
    public function testEncodeEmpty(): void
    {
        self::assertSame('[]', $this->codec->encode([]));
    }

    /**
     * A stored list decodes into items, ascending
     *
     * @return void
     */
    public function testDecode(): void
    {
        $items = $this->codec->decode(
            '[{"min_width":768,"per_page":3,"gap":"12px"},{"min_width":0,"per_page":1,"gap":null}]'
        );

        self::assertCount(2, $items);
        self::assertTrue((new ResponsiveItem(0, 1, null))->equals($items[0]));
        self::assertTrue((new ResponsiveItem(768, 3, '12px'))->equals($items[1]));
    }

    /**
     * Entries that break an item rule are left out
     *
     * @return void
     */
    public function testDecodeSkipsInvalidEntries(): void
    {
        $items = $this->codec->decode(
            '[{"min_width":0,"per_page":11,"gap":null},{"min_width":"0","per_page":1},'
            . '{"min_width":10,"per_page":2,"gap":"12"},5,{"min_width":20,"per_page":2}]'
        );

        self::assertCount(1, $items);
        self::assertSame(20, $items[0]->getMinWidth());
    }

    /**
     * Values that are not a JSON list decode to no items
     *
     * @param string|null $stored
     * @return void
     */
    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['  '])]
    #[TestWith(['{broken'])]
    #[TestWith(['{"0":{"items":2}}'])]
    #[TestWith(['{"768":{"items":3}}'])]
    #[TestWith(['42'])]
    public function testDecodeUnreadable(?string $stored): void
    {
        self::assertSame([], $this->codec->decode($stored));
    }
}
