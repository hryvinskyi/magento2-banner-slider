<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Hryvinskyi\BannerSlider\Model\Image\ImagePixelLimit;
use Hryvinskyi\BannerSlider\Model\Image\MediaImageReader;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSlider\Test\Unit\Model\ImageFormats;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaImageReader::class)]
class MediaImageReaderTest extends TestCase
{
    use ImageFormats;

    /**
     * @var LocalFileWorkspace&MockObject
     */
    private MockObject $workspace;

    /**
     * @var ImageInspector&MockObject
     */
    private MockObject $inspector;

    /**
     * @var MediaImageReader
     */
    private MediaImageReader $reader;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->workspace = $this->createMock(LocalFileWorkspace::class);
        $this->inspector = $this->createMock(ImageInspector::class);
        $this->reader = new MediaImageReader(
            $this->workspace,
            $this->inspector,
            $this->formatRegistry(),
            new ImagePixelLimit(10000000)
        );
    }

    /**
     * Size and format come from the inspected local copy, also for a file outside the package folders
     *
     * @return void
     */
    public function testReadsSizeAndFormat(): void
    {
        $this->workspace->method('withLocalCopy')->with('legacy_slider/image/a.jpeg')->willReturnCallback(
            static fn (string $path, callable $callback): mixed => $callback(
                '/srv/pub/media/legacy_slider/image/a.jpeg'
            )
        );
        $this->inspector->method('inspect')->with('/srv/pub/media/legacy_slider/image/a.jpeg')
            ->willReturn(['mime' => 'image/jpeg', 'width' => 1600, 'height' => 900]);

        $image = $this->reader->read('legacy_slider/image/a.jpeg');

        self::assertSame('legacy_slider/image/a.jpeg', $image->getPath());
        self::assertSame([1600, 900], [$image->getDimensions()->getWidth(), $image->getDimensions()->getHeight()]);
        self::assertSame('jpeg', $image->getFormat()->getCode());
    }

    /**
     * An image with more pixels than the limit is refused with its path and size
     *
     * @return void
     */
    public function testImageOverPixelLimitIsRefused(): void
    {
        $this->workspace->method('withLocalCopy')->willReturnCallback(
            static fn (string $path, callable $callback): mixed => $callback('/srv/pub/media/banner_slider/image/a.jpg')
        );
        $this->inspector->method('inspect')->willReturn(['mime' => 'image/jpeg', 'width' => 20000, 'height' => 10000]);

        $this->expectException(EncodingException::class);
        $this->expectExceptionMessage(
            'The image "banner_slider/image/a.jpg" is 20000x10000 pixels, more than the 10000000 pixels images may have'
        );
        $this->reader->read('banner_slider/image/a.jpg');
    }

    /**
     * A missing file is reported with its path
     *
     * @return void
     */
    public function testMissingFile(): void
    {
        $this->workspace->method('withLocalCopy')->willThrowException(new FileSystemException(__('missing')));

        $this->expectExceptionMessage('The image "banner_slider/image/a.png" cannot be read.');
        $this->reader->read('banner_slider/image/a.png');
    }

    /**
     * An unsafe path is reported like an unreadable one
     *
     * @return void
     */
    public function testUnsafePath(): void
    {
        $this->workspace->method('withLocalCopy')->willThrowException(new \InvalidArgumentException('unsafe'));

        $this->expectException(LocalizedException::class);
        $this->reader->read('../app/etc/env.php');
    }

    /**
     * A file that is not an image, or an image in an unregistered format, is refused
     *
     * @return void
     */
    public function testNotAnImageOrUnknownFormat(): void
    {
        $this->workspace->method('withLocalCopy')->willReturnCallback(
            static fn (string $path, callable $callback): mixed => $callback('/tmp/x')
        );
        $this->inspector->method('inspect')->willReturnOnConsecutiveCalls(
            null,
            ['mime' => 'image/bmp', 'width' => 2, 'height' => 2]
        );

        try {
            $this->reader->read('banner_slider/image/a.png');
            self::fail('A non-image must be refused.');
        } catch (LocalizedException $exception) {
            self::assertSame('The file "banner_slider/image/a.png" is not a readable image.', $exception->getMessage());
        }
        $this->expectExceptionMessage('unsupported format (image/bmp)');
        $this->reader->read('banner_slider/image/a.bmp');
    }
}
