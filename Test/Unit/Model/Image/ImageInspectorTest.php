<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageInspector::class)]
class ImageInspectorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../_files/images/';

    /**
     * Each committed 2x2 fixture reports its sniffed MIME type and size
     *
     * @param string $file
     * @param string $mime
     * @return void
     */
    #[TestWith(['2x2.png', 'image/png'])]
    #[TestWith(['2x2.jpg', 'image/jpeg'])]
    #[TestWith(['2x2.gif', 'image/gif'])]
    #[TestWith(['2x2.webp', 'image/webp'])]
    #[TestWith(['2x2.avif', 'image/avif'])]
    public function testInspectsFixture(string $file, string $mime): void
    {
        self::assertSame(
            ['mime' => $mime, 'width' => 2, 'height' => 2],
            (new ImageInspector(new LocalFileDriver()))->inspect(self::FIXTURES . $file)
        );
    }

    /**
     * A file that is not an image, or that does not exist, is not inspected as one
     *
     * @param string $file
     * @return void
     */
    #[TestWith(['not-an-image.txt'])]
    #[TestWith(['missing.png'])]
    public function testNonImage(string $file): void
    {
        self::assertNull((new ImageInspector(new LocalFileDriver()))->inspect(self::FIXTURES . $file));
    }
}
