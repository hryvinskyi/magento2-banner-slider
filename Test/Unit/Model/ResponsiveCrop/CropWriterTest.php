<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\Media\MediaStorage;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileNamer;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileStore;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputFiles;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputPlanner;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropWriteResult;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropWriter;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\EncodedImageValidator;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\OriginalFormatRule;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\ServerCropEncoder;
use Hryvinskyi\BannerSlider\Test\Unit\Model\ImageFormats;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\EncodedImage;
use Hryvinskyi\BannerSliderApi\Api\Value\FormatRequest;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CropWriter::class)]
class CropWriterTest extends TestCase
{
    use ImageFormats;

    private const ROOT = 'banner_slider/responsive/5/desktop_';

    /**
     * Format codes this server can encode
     *
     * @var list<string>
     */
    private array $encodable = ['webp', 'avif'];

    /**
     * Files present in media, by path
     *
     * @var array<string,string>
     */
    private array $media = [];

    /**
     * Paths written, in order
     *
     * @var list<string>
     */
    private array $written = [];

    /**
     * Paths deleted, in order
     *
     * @var list<string>
     */
    private array $deleted = [];

    /**
     * Format codes the server encoder was asked to render the original in
     *
     * @var list<string>
     */
    private array $rendered = [];

    /**
     * @var ImageFormatRegistryInterface&MockObject
     */
    private MockObject $registry;

    /**
     * @var EncodedImageValidator&MockObject
     */
    private MockObject $encodedImageValidator;

    /**
     * @var ServerCropEncoder&MockObject
     */
    private MockObject $serverEncoder;

    /**
     * @var MediaStorage&MockObject
     */
    private MockObject $mediaStorage;

    /**
     * @var CropWriter
     */
    private CropWriter $writer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $shipped = $this->formatRegistry();
        $registry = $this->registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('get')->willReturnCallback(static fn (string $code): ImageFormat => $shipped->get($code));
        $registry->method('has')->willReturnCallback(static fn (string $code): bool => $shipped->has($code));
        $registry->method('getVariantFormats')->willReturn($shipped->getVariantFormats());
        $registry->method('isEncodable')->willReturnCallback(
            fn (ImageFormat $format): bool => in_array($format->getCode(), $this->encodable, true)
        );

        $this->encodedImageValidator = $this->createMock(EncodedImageValidator::class);
        $this->serverEncoder = $this->createMock(ServerCropEncoder::class);
        $this->mediaStorage = $this->createMock(MediaStorage::class);
        $this->mediaStorage->method('exists')->willReturnCallback(
            fn (string $path): bool => isset($this->media[$path])
        );
        $this->mediaStorage->method('read')->willReturnCallback(fn (string $path): string => $this->media[$path]);
        $this->mediaStorage->method('write')->willReturnCallback(function (string $path, string $bytes): void {
            $this->written[] = $path;
            $this->media[$path] = $bytes;
        });
        $this->writer = $this->writerWith($this->mediaStorage);
    }

    /**
     * Without browser bytes the server renders the original and every variant; the old files become obsolete
     *
     * @return void
     */
    public function testServerEncodesEverything(): void
    {
        $this->serverEncoder->expects(self::once())->method('encode')->willReturnCallback(
            function (MediaImage $source, CropRect $rect, Dimensions $target, ?ImageFormat $original, array $variants) {
                self::assertSame([400, 200], [$target->getWidth(), $target->getHeight()]);
                self::assertSame('jpeg', $original?->getCode());
                self::assertSame(['webp'], $this->codes($variants));

                return ['jpeg' => 'jpeg bytes', 'webp' => 'webp bytes'];
            }
        );
        $current = $this->currentCrop(
            'banner_slider/responsive/5/desktop_old.jpg',
            'banner_slider/responsive/5/old.webp'
        );

        $result = $this->write($current, $this->input(['webp' => 85]), 'jpeg');

        $jpeg = self::ROOT . $this->hash('jpeg bytes') . '.jpg';
        $webp = self::ROOT . $this->hash('webp bytes') . '.webp';
        self::assertSame($jpeg, $result->getOriginalPath());
        self::assertCount(1, $result->getVariants());
        self::assertSame(['webp', 85, $webp], [
            $result->getVariants()[0]->getFormat(),
            $result->getVariants()[0]->getQuality(),
            $result->getVariants()[0]->getPath(),
        ]);
        self::assertSame([$jpeg, $webp], $result->getCreatedPaths());
        self::assertSame([$jpeg, $webp], $result->getNewPaths());
        self::assertSame(
            ['banner_slider/responsive/5/desktop_old.jpg', 'banner_slider/responsive/5/old.webp'],
            $result->getObsoletePaths()
        );
    }

    /**
     * Valid browser bytes are stored as they are and nothing is rendered
     *
     * @return void
     */
    public function testValidBrowserBytesAreUsed(): void
    {
        $this->encodedImageValidator->method('validate')->willReturnCallback(
            fn (EncodedImage $image): ImageFormat => $this->format($image->getFormatCode())
        );
        $this->serverEncoder->expects(self::never())->method('encode');

        $result = $this->writeFrom(
            null,
            $this->input(['webp' => 85], ['png' => 'browser png', 'webp' => 'browser webp']),
            'png'
        );

        self::assertSame(self::ROOT . $this->hash('browser png') . '.png', $result->getOriginalPath());
        self::assertSame('browser webp', $this->media[(string)$result->getVariants()[0]->getPath()]);
        self::assertSame([], $result->getObsoletePaths());
    }

    /**
     * Rejected browser bytes fall back to the server for that format only
     *
     * @return void
     */
    public function testInvalidBrowserBytesFallBackToServer(): void
    {
        $this->encodedImageValidator->method('validate')->willReturnCallback(
            function (EncodedImage $image): ImageFormat {
                if ($image->getFormatCode() === 'webp') {
                    throw new LocalizedException(__('The webp image cannot be decoded.'));
                }

                return $this->format($image->getFormatCode());
            }
        );
        $this->serverEncoder->expects(self::once())->method('encode')->willReturnCallback(
            function (MediaImage $source, CropRect $rect, Dimensions $target, ?ImageFormat $original, array $variants) {
                self::assertNull($original);
                self::assertSame(['webp'], $this->codes($variants));

                return ['webp' => 'server webp'];
            }
        );

        $result = $this->writeFrom(
            null,
            $this->input(['webp' => 85], ['png' => 'browser png', 'webp' => 'broken webp']),
            'png'
        );

        self::assertSame('browser png', $this->media[$result->getOriginalPath()]);
        self::assertSame('server webp', $this->media[(string)$result->getVariants()[0]->getPath()]);
    }

    /**
     * The original is JPEG for a JPEG source and PNG for every other source
     *
     * @param string $source
     * @param string $original
     * @param string $extension
     * @return void
     */
    #[TestWith(['jpeg', 'jpeg', 'jpg'])]
    #[TestWith(['png', 'png', 'png'])]
    #[TestWith(['gif', 'png', 'png'])]
    #[TestWith(['webp', 'png', 'png'])]
    #[TestWith(['avif', 'png', 'png'])]
    public function testOriginalFormatRule(string $source, string $original, string $extension): void
    {
        $this->serverEncoder->method('encode')->willReturnCallback(
            function (MediaImage $image, CropRect $rect, Dimensions $target, ?ImageFormat $format): array {
                $this->rendered[] = (string)$format?->getCode();

                return [(string)$format?->getCode() => 'original bytes'];
            }
        );

        $result = $this->write(null, $this->input([]), $source);

        self::assertSame([$original], $this->rendered);
        self::assertSame(self::ROOT . $this->hash('original bytes') . '.' . $extension, $result->getOriginalPath());
    }

    /**
     * A requested variant in the original's own format adds nothing and is ignored
     *
     * @return void
     */
    public function testVariantInOriginalFormatIgnored(): void
    {
        $this->serverEncoder->expects(self::once())->method('encode')->willReturnCallback(
            function (MediaImage $source, CropRect $rect, Dimensions $target, ?ImageFormat $original, array $variants) {
                self::assertSame(['webp'], $this->codes($variants));

                return ['png' => 'png bytes', 'webp' => 'webp bytes'];
            }
        );

        $result = $this->writeFrom(null, $this->input(['png' => 90, 'webp' => 85]), 'png');

        self::assertSame(['webp'], array_map(
            static fn ($variant): string => $variant->getFormat(),
            $result->getVariants()
        ));
    }

    /**
     * A file that already exists with the same bytes is reused: not written, not created, not obsolete
     *
     * @return void
     */
    public function testIdenticalExistingFileIsReused(): void
    {
        $existing = self::ROOT . $this->hash('png bytes') . '.png';
        $this->media[$existing] = 'png bytes';
        $this->serverEncoder->method('encode')->willReturn(['png' => 'png bytes', 'webp' => 'webp bytes']);

        $result = $this->writeFrom($this->currentCrop($existing), $this->input(['webp' => 85]), 'png');

        $webp = self::ROOT . $this->hash('webp bytes') . '.webp';
        self::assertSame([$webp], $this->written);
        self::assertSame([$webp], $result->getCreatedPaths());
        self::assertSame([$existing, $webp], $result->getNewPaths());
        self::assertSame([], $result->getObsoletePaths());
    }

    /**
     * When storing fails part-way, the files this write created are removed and the error surfaces
     *
     * @return void
     */
    public function testStoreFailureRemovesCreatedFiles(): void
    {
        $this->serverEncoder->method('encode')->willReturn(['png' => 'png bytes', 'webp' => 'webp bytes']);
        $png = self::ROOT . $this->hash('png bytes') . '.png';
        $this->mediaStorage = $this->createMock(MediaStorage::class);
        $this->mediaStorage->method('write')->willReturnCallback(function (string $path) use ($png): void {
            if ($path !== $png) {
                throw new FileSystemException(__('disk full'));
            }
        });

        $this->expectException(CouldNotSaveException::class);
        try {
            $this->writeFrom(null, $this->input(['webp' => 85]), 'png', $this->writerWith($this->mediaStorage));
        } finally {
            self::assertSame([$png], $this->deleted);
        }
    }

    /**
     * A render failure is reported as a failed save naming the breakpoint
     *
     * @return void
     */
    public function testRenderFailure(): void
    {
        $this->serverEncoder->method('encode')->willThrowException(new LocalizedException(__('adapter broke')));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('The crop for breakpoint "desktop" could not be generated: adapter broke');
        $this->writeFrom(null, $this->input([]), 'png');
    }

    /**
     * Write with breakpoint "desktop" of banner 5 from a source in the given format
     *
     * @param ResponsiveCropInterface|null $current
     * @param CropInput $input
     * @param string $sourceFormat
     * @return CropWriteResult
     */
    private function write(?ResponsiveCropInterface $current, CropInput $input, string $sourceFormat): CropWriteResult
    {
        return $this->writeFrom($current, $input, $sourceFormat);
    }

    /**
     * Plan the input with the real output planner, then write the plan for breakpoint "desktop" of banner 5
     *
     * @param ResponsiveCropInterface|null $current
     * @param CropInput $input
     * @param string $sourceFormat
     * @param CropWriter|null $writer
     * @return CropWriteResult
     */
    private function writeFrom(
        ?ResponsiveCropInterface $current,
        CropInput $input,
        string $sourceFormat,
        ?CropWriter $writer = null
    ): CropWriteResult {
        $planner = new CropOutputPlanner(
            $this->registry,
            new OriginalFormatRule($this->registry),
            new CropTargetSize(),
            $this->encodedImageValidator,
            $this->createMock(LoggerInterface::class)
        );
        $plan = $planner->plan($input, $this->breakpoint(), $this->source($sourceFormat));

        return ($writer ?? $this->writer)->write($current, $plan, 5, 'desktop');
    }

    /**
     * A writer over another media storage double
     *
     * @param MediaStorage&MockObject $mediaStorage
     * @return CropWriter
     */
    private function writerWith(MockObject $mediaStorage): CropWriter
    {
        $mediaStorage->method('delete')->willReturnCallback(function (string $path): void {
            $this->deleted[] = $path;
        });

        return new CropWriter(
            $this->registry,
            $this->serverEncoder,
            new CropFileStore(
                new CropFileNamer(new MediaPaths(['responsive' => 'banner_slider/responsive'])),
                $mediaStorage,
                $this->createMock(LoggerInterface::class)
            ),
            new CropOutputFiles($this->createMock(CollectionFactory::class))
        );
    }

    /**
     * A crop input for breakpoint 3
     *
     * @param array<string,int> $formats Quality by format code
     * @param array<string,string> $encoded Browser bytes by format code
     * @return CropInput
     */
    private function input(array $formats, array $encoded = []): CropInput
    {
        $requests = [];
        foreach ($formats as $code => $quality) {
            $requests[] = new FormatRequest($code, $quality);
        }
        $images = [];
        foreach ($encoded as $code => $bytes) {
            $images[] = new EncodedImage($code, $bytes);
        }

        return new CropInput(3, null, new CropRect(0, 0, 800, 400), $requests, $images, true, false);
    }

    /**
     * Breakpoint "desktop", rendered at 400x200
     *
     * @return BreakpointInterface
     */
    private function breakpoint(): BreakpointInterface
    {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('getIdentifier')->willReturn('desktop');
        $breakpoint->method('toSpec')->willReturn(new BreakpointSpec('desktop', '(min-width: 1200px)', 1200, 400, 200));

        return $breakpoint;
    }

    /**
     * A source image in a format
     *
     * @param string $code
     * @return MediaImage
     */
    private function source(string $code): MediaImage
    {
        return new MediaImage('banner_slider/image/a.' . $code, new Dimensions(1600, 800), $this->format($code));
    }

    /**
     * A stored crop with an original and optionally a WebP variant
     *
     * @param string $original
     * @param string|null $webp
     * @return ResponsiveCropInterface
     */
    private function currentCrop(string $original, ?string $webp = null): ResponsiveCropInterface
    {
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->method('getCroppedImage')->willReturn($original);
        $crop->method('getVariants')->willReturn($webp === null ? [] : [new CropVariant('webp', 85, $webp)]);

        return $crop;
    }

    /**
     * The format codes of format requests
     *
     * @param array<mixed> $requests
     * @return list<string>
     */
    private function codes(array $requests): array
    {
        $codes = [];
        foreach ($requests as $request) {
            self::assertInstanceOf(FormatRequest::class, $request);
            $codes[] = $request->getFormatCode();
        }

        return $codes;
    }

    /**
     * The twelve-character content hash of bytes
     *
     * @param string $bytes
     * @return string
     */
    private function hash(string $bytes): string
    {
        return substr(hash('sha256', $bytes), 0, 12);
    }
}
