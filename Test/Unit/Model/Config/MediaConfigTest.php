<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Config;

use Hryvinskyi\BannerSlider\Model\Config\MediaConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaConfig::class)]
class MediaConfigTest extends TestCase
{
    /**
     * The orphan sweep runs only on an explicit on; a missing or broken value keeps it off
     *
     * @param mixed $stored
     * @param bool $expected
     * @return void
     */
    #[TestWith(['1', true])]
    #[TestWith([1, true])]
    #[TestWith([true, true])]
    #[TestWith(['0', false])]
    #[TestWith([null, false])]
    #[TestWith(['', false])]
    #[TestWith(['yes', false])]
    public function testOrphanSweepEnabled(mixed $stored, bool $expected): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())->method('getValue')
            ->with('hryvinskyi_banner_slider/media/orphan_sweep_enabled')
            ->willReturn($stored);

        self::assertSame($expected, (new MediaConfig($scopeConfig))->isOrphanSweepEnabled());
    }
}
