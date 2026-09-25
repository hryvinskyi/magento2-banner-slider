<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\UploadedFileChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadedFileChecker::class)]
class UploadedFileCheckerTest extends TestCase
{
    /**
     * A file that exists on disk but did not arrive with this request is not an upload
     *
     * @param string $path
     * @return void
     */
    #[TestWith([__DIR__ . '/../../_files/images/2x2.png'])]
    #[TestWith([''])]
    public function testFileNotReceivedByThisRequest(string $path): void
    {
        self::assertFalse((new UploadedFileChecker())->isUploadedFile($path));
    }
}
