<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Data;

use Hryvinskyi\BannerSlider\Model\Data\MediaRelativePath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaRelativePath::class)]
class MediaRelativePathTest extends TestCase
{
    /**
     * Relative paths are kept as given, wherever they live inside media
     *
     * @param string $path
     * @return void
     */
    #[TestWith(['banner_slider/image/a.jpg'])]
    #[TestWith(['legacy_slider/image/b.png'])]
    #[TestWith(['hryvinskyi/banner_slider/c.webp'])]
    #[TestWith(['c.mp4'])]
    #[TestWith(['banner_slider/responsive/12/'])]
    #[TestWith(['a/..b/c..d.jpg'])]
    public function testSafePathIsKept(string $path): void
    {
        self::assertSame($path, (new MediaRelativePath($path))->toString());
    }

    /**
     * Empty, absolute and escaping paths are rejected
     *
     * @param string $path
     * @return void
     */
    #[TestWith([''])]
    #[TestWith(['  '])]
    #[TestWith(['/var/www/a.jpg'])]
    #[TestWith(['\\\\server\\share\\a.jpg'])]
    #[TestWith(['banner_slider\\image\\a.jpg'])]
    #[TestWith(['https://cdn.example.com/a.jpg'])]
    #[TestWith(['phar://a.jpg'])]
    #[TestWith(['C:\\media\\a.jpg'])]
    #[TestWith(['C:/media/a.jpg'])]
    #[TestWith(["a\0.jpg"])]
    #[TestWith(['../app/etc/env.php'])]
    #[TestWith(['banner_slider/../../app/etc/env.php'])]
    #[TestWith(['banner_slider/image/..'])]
    public function testUnsafePathIsRejected(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MediaRelativePath($path);
    }

    /**
     * Every spelling of one file has the same canonical form
     *
     * @param string $path
     * @param string $canonical
     * @return void
     */
    #[TestWith(['banner_slider/image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['banner_slider/image//a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['banner_slider//image///a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['./banner_slider/./image/a.jpg', 'banner_slider/image/a.jpg'])]
    #[TestWith(['banner_slider/responsive/12/', 'banner_slider/responsive/12'])]
    #[TestWith(['a/..b/c..d.jpg', 'a/..b/c..d.jpg'])]
    #[TestWith(['./', ''])]
    public function testCanonicalForm(string $path, string $canonical): void
    {
        self::assertSame($canonical, (new MediaRelativePath($path))->toCanonicalString());
    }
}
