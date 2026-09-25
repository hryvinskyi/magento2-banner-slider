<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\CropRenderer;
use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\ImageConverter;
use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Hryvinskyi\BannerSlider\Model\Image\ImagePixelLimit;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Image\Adapter\AbstractAdapter;
use Magento\Framework\Image\Adapter\AdapterInterface;
use Magento\Framework\Image\AdapterFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropRenderer::class)]
class CropRendererTest extends TestCase
{
    private const LOCAL_SOURCE = '/srv/pub/media/banner_slider/image/source';

    /**
     * @var LocalFileWorkspace&MockObject
     */
    private MockObject $workspace;

    /**
     * @var ImageInspector&MockObject
     */
    private MockObject $inspector;

    /**
     * @var ImageConverter&MockObject
     */
    private MockObject $converter;

    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $adapter;

    /**
     * @var AdapterFactory&MockObject
     */
    private MockObject $adapterFactory;

    /**
     * @var ImageConfigInterface&MockObject
     */
    private MockObject $imageConfig;

    /**
     * @var CropRenderer
     */
    private CropRenderer $renderer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->workspace = $this->createMock(LocalFileWorkspace::class);
        $this->workspace->method('withLocalCopy')->willReturnCallback(
            static fn (string $mediaPath, callable $callback): mixed => $callback(self::LOCAL_SOURCE)
        );
        $counter = 0;
        $this->workspace->method('newTempPath')->willReturnCallback(
            static function (string $extension) use (&$counter): string {
                $counter++;

                return '/srv/var/tmp/' . $counter . '.' . $extension;
            }
        );
        $this->inspector = $this->createMock(ImageInspector::class);
        $registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('getByMimeType')->willReturnCallback(
            static fn (string $mime): ?ImageFormat => match ($mime) {
                'image/jpeg' => new ImageFormat('jpeg', 'image/jpeg', 'jpg'),
                'image/png' => new ImageFormat('png', 'image/png', 'png'),
                'image/webp' => new ImageFormat('webp', 'image/webp', 'webp'),
                default => null,
            }
        );
        $this->converter = $this->createMock(ImageConverter::class);
        $this->adapter = $this->createMock(AdapterInterface::class);
        $this->adapterFactory = $this->createMock(AdapterFactory::class);
        $this->adapterFactory->method('create')->willReturn($this->adapter);
        $this->imageConfig = $this->createMock(ImageConfigInterface::class);
        $this->imageConfig->method('getDefaultQuality')->willReturnMap([['jpeg', 90]]);
        $this->renderer = new CropRenderer(
            $this->workspace,
            $this->inspector,
            $registry,
            $this->converter,
            $this->adapterFactory,
            new ImagePixelLimit(1000000),
            $this->imageConfig,
            ['jpeg' => 'jpeg', 'png' => 'png'],
            ['jpeg' => 'jpeg']
        );
    }

    /**
     * A fitting rectangle is cut by its margins and scaled to the target size, in the source's own format
     *
     * @return void
     */
    public function testRendersFittingRect(): void
    {
        $this->givenSource('image/jpeg', 100, 50);
        $this->converter->expects(self::never())->method('convert');
        $this->adapter->expects(self::once())->method('open')->with(self::LOCAL_SOURCE);
        $this->adapter->expects(self::once())->method('crop')->with(10, 20, 30, 15);
        $this->adapter->expects(self::once())->method('resize')->with(200, 120);
        $this->adapter->expects(self::once())->method('save')->with('/srv/var/tmp/1.jpg');
        $this->workspace->expects(self::never())->method('discard');

        $output = $this->renderer->render(
            'banner_slider/image/a.jpg',
            new CropRect(20, 10, 50, 25),
            200,
            120,
            $this->jpeg()
        );

        self::assertSame('/srv/var/tmp/1.jpg', $output);
    }

    /**
     * Without a target height the height follows the rectangle's aspect ratio
     *
     * @param int $rectWidth
     * @param int $rectHeight
     * @param int $targetWidth
     * @param int $expectedHeight
     * @return void
     */
    #[TestWith([50, 25, 200, 100])]
    #[TestWith([3, 2, 100, 67])]
    #[TestWith([100, 1, 10, 1])]
    public function testNullTargetHeightKeepsAspectRatio(
        int $rectWidth,
        int $rectHeight,
        int $targetWidth,
        int $expectedHeight
    ): void {
        $this->givenSource('image/png', 100, 50);
        $this->adapter->expects(self::once())->method('resize')->with($targetWidth, $expectedHeight);

        $this->renderer->render(
            'banner_slider/image/a.png',
            new CropRect(0, 0, $rectWidth, $rectHeight),
            $targetWidth,
            null,
            $this->png()
        );
    }

    /**
     * A rectangle outside the source fails with the breakpoint size and no server path
     *
     * @return void
     */
    public function testRectOutsideSourceFails(): void
    {
        $this->givenSource('image/png', 100, 50);
        $this->adapterFactory->expects(self::never())->method('create');

        try {
            $this->renderer->render('banner_slider/image/a.png', new CropRect(60, 0, 50, 25), 768, 384, $this->png());
            self::fail('The render should have failed.');
        } catch (LocalizedException $e) {
            self::assertSame(
                'The crop area 50x25 at 60,0 for the 768x384 breakpoint does not fit inside the 100x50 source image.',
                $e->getMessage()
            );
            self::assertStringNotContainsString('/srv/', $e->getMessage());
        }
    }

    /**
     * A JPEG crop is saved at the configured JPEG quality; a PNG crop keeps the adapter's own setting
     *
     * @return void
     */
    public function testJpegOutputUsesConfiguredQuality(): void
    {
        $adapter = $this->createMock(AbstractAdapter::class);
        $adapter->expects(self::once())->method('quality')->with(90);
        $adapterFactory = $this->createMock(AdapterFactory::class);
        $adapterFactory->method('create')->willReturn($adapter);
        $this->givenSource('image/jpeg', 100, 50);

        $this->rendererWith($adapterFactory)
            ->render('banner_slider/image/a.jpg', new CropRect(0, 0, 100, 50), 50, 25, $this->jpeg());

        $pngAdapter = $this->createMock(AbstractAdapter::class);
        $pngAdapter->expects(self::never())->method('quality');
        $pngFactory = $this->createMock(AdapterFactory::class);
        $pngFactory->method('create')->willReturn($pngAdapter);
        $this->rendererWith($pngFactory)
            ->render('banner_slider/image/a.jpg', new CropRect(0, 0, 100, 50), 50, 25, $this->png());
    }

    /**
     * A source with more pixels than the limit is refused before the adapter decodes it
     *
     * @return void
     */
    public function testSourceOverPixelLimitIsRefused(): void
    {
        $this->givenSource('image/jpeg', 2000, 1000);
        $this->adapterFactory->expects(self::never())->method('create');
        $this->workspace->expects(self::never())->method('newTempPath');

        $this->expectException(EncodingException::class);
        $this->expectExceptionMessage('The image is 2000x1000 pixels');
        $this->renderer->render('banner_slider/image/a.jpg', new CropRect(0, 0, 100, 50), 50, 25, $this->jpeg());
    }

    /**
     * An output with more pixels than the limit is refused before the source is read
     *
     * @return void
     */
    public function testOutputOverPixelLimitIsRefused(): void
    {
        $this->workspace->expects(self::never())->method('withLocalCopy');

        $this->expectException(EncodingException::class);
        $this->renderer->render('banner_slider/image/a.jpg', new CropRect(0, 0, 1, 1000), 1001, null, $this->jpeg());
    }

    /**
     * A source in another format is re-encoded into the output format first, and the intermediate file removed
     *
     * @return void
     */
    public function testSourceInOtherFormatIsConvertedFirst(): void
    {
        $this->givenSource('image/webp', 100, 50);
        $this->converter->expects(self::once())->method('convert')
            ->with(self::LOCAL_SOURCE, $this->png(), 100, '/srv/var/tmp/2.png');
        $this->adapter->expects(self::once())->method('open')->with('/srv/var/tmp/2.png');
        $this->adapter->expects(self::once())->method('save')->with('/srv/var/tmp/1.png');
        $this->workspace->expects(self::once())->method('discard')->with('/srv/var/tmp/2.png');

        self::assertSame(
            '/srv/var/tmp/1.png',
            $this->renderer->render('banner_slider/image/a.webp', new CropRect(0, 0, 100, 50), 50, 25, $this->png())
        );
    }

    /**
     * An adapter failure removes the unfinished output and hides the adapter's message
     *
     * @return void
     */
    public function testAdapterFailureCleansUp(): void
    {
        $this->givenSource('image/png', 100, 50);
        $this->adapter->method('save')->willThrowException(
            new \DomainException('Unable to write file into directory /srv/var/tmp. Access forbidden.')
        );
        $this->workspace->expects(self::once())->method('discard')->with('/srv/var/tmp/1.png');

        try {
            $this->renderer->render('banner_slider/image/a.png', new CropRect(0, 0, 100, 50), 50, 25, $this->png());
            self::fail('The render should have failed.');
        } catch (LocalizedException $e) {
            self::assertSame('The crop for the 50x25 breakpoint could not be rendered.', $e->getMessage());
            self::assertInstanceOf(\DomainException::class, $e->getPrevious());
        }
    }

    /**
     * A source that is not an image in a known format fails before anything is written
     *
     * @return void
     */
    public function testUnreadableSourceFails(): void
    {
        $this->inspector->method('inspect')->willReturn(null);
        $this->workspace->expects(self::never())->method('newTempPath');

        $this->expectException(LocalizedException::class);
        $this->renderer->render('banner_slider/image/a.png', new CropRect(0, 0, 1, 1), 50, 25, $this->png());
    }

    /**
     * An output format the adapter does not write, or a target side of zero, fails before the source is read
     *
     * @param string $format
     * @param int $width
     * @param int|null $height
     * @return void
     */
    #[TestWith(['webp', 100, 50])]
    #[TestWith(['png', 0, 50])]
    #[TestWith(['png', 100, 0])]
    public function testInvalidRequestFails(string $format, int $width, ?int $height): void
    {
        $this->workspace->expects(self::never())->method('withLocalCopy');

        $this->expectException(LocalizedException::class);
        $this->renderer->render(
            'banner_slider/image/a.png',
            new CropRect(0, 0, 10, 10),
            $width,
            $height,
            new ImageFormat($format, 'image/' . $format, $format)
        );
    }

    /**
     * The source the inspector reports
     *
     * @param string $mime
     * @param int $width
     * @param int $height
     * @return void
     */
    private function givenSource(string $mime, int $width, int $height): void
    {
        $this->inspector->method('inspect')->with(self::LOCAL_SOURCE)
            ->willReturn(['mime' => $mime, 'width' => $width, 'height' => $height]);
    }

    /**
     * The JPEG format
     *
     * @return ImageFormat
     */
    private function jpeg(): ImageFormat
    {
        return new ImageFormat('jpeg', 'image/jpeg', 'jpg');
    }

    /**
     * The PNG format
     *
     * @return ImageFormat
     */
    private function png(): ImageFormat
    {
        return new ImageFormat('png', 'image/png', 'png');
    }

    /**
     * A renderer like the one under test over another adapter factory
     *
     * @param AdapterFactory $adapterFactory
     * @return CropRenderer
     */
    private function rendererWith(AdapterFactory $adapterFactory): CropRenderer
    {
        $registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('getByMimeType')->willReturn(new ImageFormat('jpeg', 'image/jpeg', 'jpg'));

        return new CropRenderer(
            $this->workspace,
            $this->inspector,
            $registry,
            $this->converter,
            $adapterFactory,
            new ImagePixelLimit(1000000),
            $this->imageConfig,
            ['jpeg' => 'jpeg', 'png' => 'png'],
            ['jpeg' => 'jpeg']
        );
    }
}
