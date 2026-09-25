<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Cron;

use Hryvinskyi\BannerSlider\Cron\RefreshScheduledContent;
use Hryvinskyi\BannerSlider\Model\Cache\ScheduleBoundaryRefresher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshScheduledContent::class)]
class RefreshScheduledContentTest extends TestCase
{
    /**
     * The job runs one refresh
     *
     * @return void
     */
    public function testExecuteRefreshes(): void
    {
        $refresher = $this->createMock(ScheduleBoundaryRefresher::class);
        $refresher->expects(self::once())->method('refresh')->willReturn([]);

        (new RefreshScheduledContent($refresher))->execute();
    }

    /**
     * The job is scheduled every five minutes and points at this class
     *
     * @return void
     */
    public function testCrontabEntry(): void
    {
        $crontab = simplexml_load_file(dirname(__DIR__, 3) . '/etc/crontab.xml');
        self::assertNotFalse($crontab);
        $job = $crontab->xpath('//job[@name="hryvinskyi_banner_slider_refresh_scheduled_content"]');
        self::assertIsArray($job);
        self::assertCount(1, $job);
        self::assertSame(RefreshScheduledContent::class, (string)$job[0]['instance']);
        self::assertSame('execute', (string)$job[0]['method']);
        self::assertSame('*/5 * * * *', (string)$job[0]->schedule);
    }
}
