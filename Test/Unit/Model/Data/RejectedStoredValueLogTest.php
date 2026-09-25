<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Data;

use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(RejectedStoredValueLog::class)]
class RejectedStoredValueLogTest extends TestCase
{
    /**
     * Debug messages logged, in order
     *
     * @var list<string>
     */
    private array $messages = [];

    /**
     * Each entity, id and field is logged once; other ids, fields and unsaved rows are logged on their own
     *
     * @return void
     */
    public function testLogsOncePerEntityIdAndField(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug')->willReturnCallback(function (string $message): void {
            $this->messages[] = $message;
        });
        $log = new RejectedStoredValueLog($logger);

        $log->report('banner', 9, 'link_url', 'bad scheme');
        $log->report('banner', 9, 'link_url', 'bad scheme');
        $log->report('banner', 10, 'link_url', 'bad scheme');
        $log->report('banner', 9, 'video_url', 'bad scheme');
        $log->report('slider', null, 'custom_css', 'contains "<"');

        self::assertSame(
            [
                'Banner slider: the stored link_url of banner 9 is read as empty: bad scheme',
                'Banner slider: the stored link_url of banner 10 is read as empty: bad scheme',
                'Banner slider: the stored video_url of banner 9 is read as empty: bad scheme',
                'Banner slider: the stored custom_css of slider (not saved) is read as empty: contains "<"',
            ],
            $this->messages
        );
    }

    /**
     * After a request reset the same refusal is logged again
     *
     * @return void
     */
    public function testResetLogsAgain(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('debug');
        $log = new RejectedStoredValueLog($logger);

        $log->report('banner', 9, 'link_url', 'bad scheme');
        $log->_resetState();
        $log->report('banner', 9, 'link_url', 'bad scheme');
    }
}
