<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Migration;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyLocationNormaliser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyLocationNormaliser::class)]
class LegacyLocationNormaliserTest extends TestCase
{
    /**
     * Valid codes are kept; invalid characters become `_`, surrounding whitespace goes, nothing left is null
     *
     * @param string $stored
     * @param string|null $expected
     * @return void
     */
    #[TestWith(['homepage_slider_main', 'homepage_slider_main'])]
    #[TestWith(['Home-Top_1', 'Home-Top_1'])]
    #[TestWith(['home page', 'home_page'])]
    #[TestWith([' home.top ', 'home_top'])]
    #[TestWith(['café/top', 'caf__top'])]
    #[TestWith(['   ', null])]
    #[TestWith(['', null])]
    public function testNormalise(string $stored, ?string $expected): void
    {
        self::assertSame($expected, (new LegacyLocationNormaliser())->normalise($stored));
    }

    /**
     * A location longer than a code may be is cut to 255 characters
     *
     * @return void
     */
    public function testLongLocationIsCut(): void
    {
        $code = (new LegacyLocationNormaliser())->normalise(str_repeat('a b', 100));

        self::assertSame(substr(str_repeat('a_b', 100), 0, 255), $code);
    }

    /**
     * A location that is not valid UTF-8 is still rewritten byte by byte
     *
     * @return void
     */
    public function testInvalidUtf8IsRewrittenByteByByte(): void
    {
        self::assertSame('home_top', (new LegacyLocationNormaliser())->normalise("home\xFFtop"));
    }
}
