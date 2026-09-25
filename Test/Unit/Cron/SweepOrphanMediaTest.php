<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Cron;

use Hryvinskyi\BannerSlider\Cron\SweepOrphanMedia;
use Hryvinskyi\BannerSlider\Model\Config\MediaConfig;
use Hryvinskyi\BannerSlider\Model\Media\OrphanMediaSweeper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SweepOrphanMedia::class)]
class SweepOrphanMediaTest extends TestCase
{
    /**
     * While the sweep is off the job does nothing
     *
     * @return void
     */
    public function testDisabledDoesNothing(): void
    {
        $config = $this->createMock(MediaConfig::class);
        $config->method('isOrphanSweepEnabled')->willReturn(false);
        $sweeper = $this->createMock(OrphanMediaSweeper::class);
        $sweeper->expects(self::never())->method('sweep');

        (new SweepOrphanMedia($config, $sweeper))->execute();
    }

    /**
     * Once enabled the job deletes, not a dry run
     *
     * @return void
     */
    public function testEnabledSweeps(): void
    {
        $config = $this->createMock(MediaConfig::class);
        $config->method('isOrphanSweepEnabled')->willReturn(true);
        $sweeper = $this->createMock(OrphanMediaSweeper::class);
        $sweeper->expects(self::once())->method('sweep')->with(false)->willReturn([]);

        (new SweepOrphanMedia($config, $sweeper))->execute();
    }
}
