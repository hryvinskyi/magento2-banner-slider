<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Config;

use Hryvinskyi\BannerSlider\Model\Config\UploadLimitReader;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadLimitReader::class)]
class UploadLimitReaderTest extends TestCase
{
    /**
     * A positive number of megabytes becomes bytes; anything else gives the fallback
     *
     * @param mixed $stored
     * @param int $expected
     * @return void
     */
    #[TestWith(['1', 1048576])]
    #[TestWith([3, 3145728])]
    #[TestWith(['0.5', 524288])]
    #[TestWith(['0.0000001', 1])]
    #[TestWith(['0', 2097152])]
    #[TestWith(['-5', 2097152])]
    #[TestWith(['', 2097152])]
    #[TestWith(['ten', 2097152])]
    #[TestWith([null, 2097152])]
    public function testReadBytes(mixed $stored, int $expected): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())->method('getValue')->with('some/limit_mb')->willReturn($stored);

        self::assertSame($expected, (new UploadLimitReader($scopeConfig))->readBytes('some/limit_mb', 2.0));
    }
}
