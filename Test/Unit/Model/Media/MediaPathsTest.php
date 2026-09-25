<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaPaths::class)]
class MediaPathsTest extends TestCase
{
    /**
     * Safe paths are returned unchanged, including legacy folders outside the package roots
     *
     * @param string $path
     * @return void
     */
    #[TestWith(['banner_slider/image/2026/09/a.jpg'])]
    #[TestWith(['legacy_slider/image/b.png'])]
    #[TestWith(['hryvinskyi/banner_slider/c.jpg'])]
    #[TestWith(['wysiwyg/d.png'])]
    public function testAssertSafeAcceptsAnySafePath(string $path): void
    {
        self::assertSame($path, $this->paths()->assertSafe($path));
    }

    /**
     * Unsafe paths are refused for reads
     *
     * @param string $path
     * @return void
     */
    #[TestWith([''])]
    #[TestWith(['/banner_slider/image/a.jpg'])]
    #[TestWith(['banner_slider/image/../../../app/etc/env.php'])]
    #[TestWith(['..'])]
    #[TestWith(["banner_slider/image/a\0.jpg"])]
    #[TestWith(['banner_slider\\image\\a.jpg'])]
    #[TestWith(['https://example.com/a.jpg'])]
    #[TestWith(['phar://banner_slider/a.phar'])]
    public function testAssertSafeRejectsUnsafePath(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->paths()->assertSafe($path);
    }

    /**
     * Paths inside a package root, and a root itself, are writable
     *
     * @param string $path
     * @return void
     */
    #[TestWith(['banner_slider/image/2026/09/a.jpg'])]
    #[TestWith(['banner_slider/responsive/12/desktop_abc.webp'])]
    #[TestWith(['banner_slider/video/a.mp4'])]
    #[TestWith(['banner_slider/breakpoint/a.png'])]
    #[TestWith(['banner_slider/tmp/a.png'])]
    #[TestWith(['banner_slider/responsive'])]
    #[TestWith(['banner_slider/responsive/12/'])]
    public function testAssertWritableAcceptsPathInsideRoot(string $path): void
    {
        self::assertSame($path, $this->paths()->assertWritable($path));
    }

    /**
     * Safe paths outside the roots are readable but not writable; unsafe paths are neither
     *
     * @param string $path
     * @return void
     */
    #[TestWith(['legacy_slider/image/b.png'])]
    #[TestWith(['hryvinskyi/banner_slider/c.jpg'])]
    #[TestWith(['banner_slider/a.jpg'])]
    #[TestWith(['banner_slider/images/a.jpg'])]
    #[TestWith(['banner_slider'])]
    #[TestWith(['banner_slider/image/../../app/etc/env.php'])]
    #[TestWith(['/banner_slider/image/a.jpg'])]
    #[TestWith(["banner_slider/image/a\0.jpg"])]
    #[TestWith(['banner_slider\\image\\a.jpg'])]
    #[TestWith(['file://banner_slider/image/a.jpg'])]
    #[TestWith([''])]
    public function testAssertWritableRejectsPathOutsideRoots(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->paths()->assertWritable($path);
    }

    /**
     * Roots are looked up by purpose, listed, and recognised as roots
     *
     * @return void
     */
    public function testRoots(): void
    {
        $paths = $this->paths();

        self::assertSame('banner_slider/responsive', $paths->getRoot('responsive'));
        self::assertCount(5, $paths->getRoots());
        self::assertTrue($paths->isRoot('banner_slider/image/'));
        self::assertFalse($paths->isRoot('banner_slider/image/2026'));

        $this->expectException(\InvalidArgumentException::class);
        $paths->getRoot('unknown');
    }

    /**
     * A root that is not a safe media path fails when the service is built
     *
     * @return void
     */
    public function testUnsafeRootIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MediaPaths(['image' => '../outside']);
    }

    /**
     * The service with the package roots
     *
     * @return MediaPaths
     */
    private function paths(): MediaPaths
    {
        return new MediaPaths([
            'image' => 'banner_slider/image',
            'responsive' => 'banner_slider/responsive/',
            'video' => 'banner_slider/video',
            'breakpoint' => 'banner_slider/breakpoint',
            'tmp' => 'banner_slider/tmp',
        ]);
    }
}
