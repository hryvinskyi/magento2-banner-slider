<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\RuntimeCapabilities;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuntimeCapabilities::class)]
class RuntimeCapabilitiesTest extends TestCase
{
    /**
     * Probes answer for what exists and deny what does not
     *
     * @return void
     */
    public function testProbes(): void
    {
        $runtime = new RuntimeCapabilities();

        self::assertTrue($runtime->hasFunction('strlen'));
        self::assertFalse($runtime->hasFunction('hbs_no_such_function'));
        self::assertFalse($runtime->gdSupports('HBS No Such Feature'));
        self::assertFalse($runtime->imagickSupports('HBSNOSUCHFORMAT'));
    }
}
