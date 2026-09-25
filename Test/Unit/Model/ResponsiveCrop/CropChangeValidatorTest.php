<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSlider\Model\Image\MediaImageReader;
use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeSet;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropChangeValidator;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputPlanner;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\EncodedImageValidator;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\OriginalFormatRule;
use Hryvinskyi\BannerSlider\Test\Unit\Model\ImageFormats;
use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CropChangeValidator::class)]
#[CoversClass(CropChangeSet::class)]
class CropChangeValidatorTest extends TestCase
{
    use ImageFormats;

    private const BANNER_IMAGE = 'banner_slider/image/2026/09/banner.jpg';

    /**
     * Format codes this server can encode
     *
     * @var list<string>
     */
    private array $encodable = ['webp', 'avif'];

    /**
     * Paths read, in order
     *
     * @var list<string>
     */
    private array $read = [];

    /**
     * @var CropChangeValidator
     */
    private CropChangeValidator $validator;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $breakpointRepository = $this->createMock(BreakpointRepositoryInterface::class);
        $breakpointRepository->method('getBySliderId')->with(2)->willReturn([
            $this->breakpoint(3, 'desktop'),
            $this->breakpoint(4, 'mobile'),
        ]);
        $reader = $this->createMock(MediaImageReader::class);
        $reader->method('read')->willReturnCallback(function (string $path): MediaImage {
            $this->read[] = $path;
            if (str_contains($path, 'missing')) {
                throw new LocalizedException(__('The image "%1" cannot be read.', $path));
            }

            return new MediaImage(
                $path,
                new Dimensions(1600, 800),
                $this->format(str_ends_with($path, '.jpg') ? 'jpeg' : 'png')
            );
        });
        $shipped = $this->formatRegistry();
        $registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('get')->willReturnCallback(static fn (string $code): ImageFormat => $shipped->get($code));
        $registry->method('getVariantFormats')->willReturn($shipped->getVariantFormats());
        $registry->method('isEncodable')->willReturnCallback(
            fn (ImageFormat $format): bool => in_array($format->getCode(), $this->encodable, true)
        );

        $encodedImageValidator = $this->createMock(EncodedImageValidator::class);
        $encodedImageValidator->method('validate')->willReturnCallback(
            static fn (EncodedImage $image): ImageFormat => $shipped->get($image->getFormatCode())
        );

        $this->validator = new CropChangeValidator(
            $breakpointRepository,
            $reader,
            new MediaPaths([
                'image' => 'banner_slider/image',
                'responsive' => 'banner_slider/responsive',
                'breakpoint' => 'banner_slider/breakpoint',
            ]),
            new CropOutputPlanner(
                $registry,
                new OriginalFormatRule($registry),
                new CropTargetSize(),
                $encodedImageValidator,
                $this->createMock(LoggerInterface::class)
            )
        );
    }

    /**
     * A crop of the banner image passes and carries its breakpoint, stored crop and source
     *
     * @return void
     */
    public function testValidCropOfBannerImage(): void
    {
        $stored = $this->createMock(ResponsiveCropInterface::class);

        $set = $this->validator->check($this->banner(), [$this->input(3)], [3 => $stored], self::BANNER_IMAGE);

        self::assertSame([], $set->getErrors());
        self::assertCount(1, $set->getChanges());
        $change = $set->getChanges()[0];
        self::assertSame('desktop', $change->getBreakpoint()->getIdentifier());
        self::assertSame($stored, $change->getCurrent());
        $output = $change->getOutput();
        self::assertNotNull($output);
        self::assertSame(self::BANNER_IMAGE, $output->getSource()->getPath());
        self::assertSame([1920, 600], [$output->getTarget()->getWidth(), $output->getTarget()->getHeight()]);
        self::assertSame('jpeg', $output->getOriginal()->getCode());
        self::assertSame(['webp'], array_map(
            static fn (FormatRequest $request): string => $request->getFormatCode(),
            $output->getVariantsToEncode()
        ));
    }

    /**
     * A removal needs no source and reads nothing
     *
     * @return void
     */
    public function testRemoval(): void
    {
        $set = $this->validator->check(
            $this->banner(),
            [new CropInput(4, null, null, [], [], false, true)],
            [],
            self::BANNER_IMAGE
        );

        self::assertSame([], $set->getErrors());
        self::assertNull($set->getChanges()[0]->getOutput());
        self::assertSame([], $this->read);
    }

    /**
     * The allowed sources: the stored crop's source, the banner image, and the package upload folders
     *
     * @param string $source
     * @return void
     */
    #[TestWith(['legacy_slider/image/stored-source.png'])]
    #[TestWith([self::BANNER_IMAGE])]
    #[TestWith(['banner_slider/image/2026/09/other.png'])]
    #[TestWith(['banner_slider/breakpoint/old.png'])]
    public function testAllowedSource(string $source): void
    {
        $stored = $this->createMock(ResponsiveCropInterface::class);
        $stored->method('getSourceImage')->willReturn('legacy_slider/image/stored-source.png');

        $set = $this->validator->check(
            $this->banner(),
            [$this->input(3, $source)],
            [3 => $stored],
            self::BANNER_IMAGE
        );

        self::assertSame([], $set->getErrors());
        self::assertSame($source, $set->getChanges()[0]->getOutput()?->getSource()->getPath());
    }

    /**
     * Any other media file is refused before it is read, so protected files cannot be published by cropping them
     *
     * @param string $source
     * @return void
     */
    #[TestWith(['downloadable/files/secret.png'])]
    #[TestWith(['customer/a/b/id-card.png'])]
    #[TestWith(['banner_slider/responsive/9/desktop_abc.png'])]
    #[TestWith(['legacy_slider/image/stored-source.png'])]
    #[TestWith(['../app/etc/env.php'])]
    public function testForeignSourceRefused(string $source): void
    {
        $set = $this->validator->check($this->banner(), [$this->input(3, $source)], [], self::BANNER_IMAGE);

        self::assertSame([], $set->getChanges());
        self::assertSame(
            [sprintf(
                'The crop for breakpoint "desktop" may not use the image "%s". '
                . 'Use the banner image or upload an image.',
                $source
            )],
            $this->messages($set)
        );
        self::assertSame([], $this->read);
    }

    /**
     * The banner image is a crop source only while it is the stored one, or when it is an upload; a banner pointed at
     * a protected file in the same save cannot have it cut
     *
     * @param string $image The banner image as it will be saved
     * @param string|null $storedImage The stored banner image, null for a new banner
     * @param bool $accepted
     * @return void
     */
    #[TestWith(['downloadable/files/secret.png', 'banner_slider/image/old.jpg', false])]
    #[TestWith(['downloadable/files/secret.png', null, false])]
    #[TestWith(['legacy_slider/image/legacy.jpg', 'legacy_slider/image/legacy.jpg', true])]
    #[TestWith(['banner_slider/image/2026/09/new-1a2b3c4d5e6f.jpg', 'legacy_slider/image/legacy.jpg', true])]
    #[TestWith(['banner_slider/image/2026/09/new-1a2b3c4d5e6f.jpg', null, true])]
    public function testBannerImageAsSource(string $image, ?string $storedImage, bool $accepted): void
    {
        $set = $this->validator->check($this->banner($image), [$this->input(3)], [], $storedImage);

        self::assertCount($accepted ? 1 : 0, $set->getChanges());
        self::assertSame(
            $accepted ? [] : [sprintf(
                'The crop for breakpoint "desktop" may not use the image "%s". '
                . 'Use the banner image or upload an image.',
                $image
            )],
            $this->messages($set)
        );
        self::assertSame($accepted ? [$image] : [], $this->read);
    }

    /**
     * Every problem of every input is reported at once, and an image used twice is read once
     *
     * @return void
     */
    public function testCollectsEveryError(): void
    {
        $banner = $this->banner();
        $set = $this->validator->check($banner, [
            $this->input(3),
            $this->input(3),
            $this->input(9),
            $this->input(4, 'banner_slider/image/missing.png'),
        ], [], self::BANNER_IMAGE);

        self::assertCount(1, $set->getChanges());
        self::assertSame(
            [
                'More than one crop is given for breakpoint 3.',
                'Breakpoint 9 does not belong to the slider of this banner.',
                'The crop for breakpoint "mobile" cannot use its image: '
                    . 'The image "banner_slider/image/missing.png" cannot be read.',
            ],
            $this->messages($set)
        );
        self::assertSame([self::BANNER_IMAGE, 'banner_slider/image/missing.png'], $this->read);
    }

    /**
     * A banner without an image leaves a crop without a source image
     *
     * @return void
     */
    public function testNoImageAtAll(): void
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getSliderId')->willReturn(2);
        $banner->method('getImage')->willReturn(null);

        self::assertSame(
            ['The crop for breakpoint "desktop" has no image to cut: the banner has no image.'],
            $this->messages($this->validator->check($banner, [$this->input(3)], [], null))
        );
    }

    /**
     * A crop area that leaves the source image is refused
     *
     * @return void
     */
    public function testAreaOutsideSource(): void
    {
        $input = new CropInput(3, null, new CropRect(1000, 0, 700, 400), [], [], true, false);

        self::assertSame(
            ['The crop area 700x400 at 1000,0 for breakpoint "desktop" does not fit inside the 1600x800 source image.'],
            $this->messages($this->validator->check($this->banner(), [$input], [], self::BANNER_IMAGE))
        );
    }

    /**
     * Requested formats: a non-variant format and an unencodable one without browser bytes are refused
     *
     * @return void
     */
    public function testFormats(): void
    {
        $this->encodable = ['webp'];
        $formats = [new FormatRequest('jpeg', 90), new FormatRequest('gif', 80), new FormatRequest('avif', 60)];

        $refused = $this->validator->check(
            $this->banner(),
            [new CropInput(3, null, new CropRect(0, 0, 800, 400), $formats, [], true, false)],
            [],
            self::BANNER_IMAGE
        );
        $supplied = $this->validator->check(
            $this->banner(),
            [new CropInput(
                3,
                null,
                new CropRect(0, 0, 800, 400),
                [new FormatRequest('avif', 60)],
                [new EncodedImage('avif', 'browser avif')],
                true,
                false
            )],
            [],
            self::BANNER_IMAGE
        );

        self::assertSame(
            [
                'The crop for breakpoint "desktop" asks for the format "gif", '
                    . 'which is not an available variant format.',
                'The crop for breakpoint "desktop" cannot be produced as avif: this server has no encoder for the '
                    . 'format. Untick the format or install an encoder.',
            ],
            $this->messages($refused)
        );
        self::assertSame([], $supplied->getErrors());
    }

    /**
     * An input for breakpoint 3 or 4 of slider 2
     *
     * @param int $breakpointId
     * @param string|null $source
     * @return CropInput
     */
    private function input(int $breakpointId, ?string $source = null): CropInput
    {
        return new CropInput(
            $breakpointId,
            $source,
            new CropRect(0, 0, 800, 400),
            [new FormatRequest('webp', 85)],
            [],
            true,
            false
        );
    }

    /**
     * A banner of slider 2 with a JPEG image
     *
     * @param string $image
     * @return BannerInterface
     */
    private function banner(string $image = self::BANNER_IMAGE): BannerInterface
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getSliderId')->willReturn(2);
        $banner->method('getImage')->willReturn($image);

        return $banner;
    }

    /**
     * A breakpoint of slider 2
     *
     * @param int $breakpointId
     * @param string $identifier
     * @return BreakpointInterface
     */
    private function breakpoint(int $breakpointId, string $identifier): BreakpointInterface
    {
        $breakpoint = $this->createMock(BreakpointInterface::class);
        $breakpoint->method('getBreakpointId')->willReturn($breakpointId);
        $breakpoint->method('getIdentifier')->willReturn($identifier);
        $breakpoint->method('toSpec')->willReturn(new BreakpointSpec($identifier, 'all', 0, 1920, 600));

        return $breakpoint;
    }

    /**
     * The rendered error messages of a check
     *
     * @param CropChangeSet $set
     * @return list<string>
     */
    private function messages(CropChangeSet $set): array
    {
        return array_map(static fn (Phrase $error): string => $error->render(), $set->getErrors());
    }
}
