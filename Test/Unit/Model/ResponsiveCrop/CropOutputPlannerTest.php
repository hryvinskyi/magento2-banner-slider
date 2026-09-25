<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputPlan;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputPlanner;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\EncodedImageValidator;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\OriginalFormatRule;
use Hryvinskyi\BannerSlider\Test\Unit\Model\ImageFormats;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\EncodedImage;
use Hryvinskyi\BannerSliderApi\Api\Value\FormatRequest;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CropOutputPlanner::class)]
#[CoversClass(CropOutputPlan::class)]
class CropOutputPlannerTest extends TestCase
{
    use ImageFormats;

    /**
     * Format codes this server can encode
     *
     * @var list<string>
     */
    private array $encodable = ['webp', 'avif'];

    /**
     * @var EncodedImageValidator&MockObject
     */
    private MockObject $encodedImageValidator;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var CropOutputPlanner
     */
    private CropOutputPlanner $planner;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $shipped = $this->formatRegistry();
        $registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('get')->willReturnCallback(static fn (string $code): ImageFormat => $shipped->get($code));
        $registry->method('getVariantFormats')->willReturn($shipped->getVariantFormats());
        $registry->method('isEncodable')->willReturnCallback(
            fn (ImageFormat $format): bool => in_array($format->getCode(), $this->encodable, true)
        );
        $this->encodedImageValidator = $this->createMock(EncodedImageValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->planner = new CropOutputPlanner(
            $registry,
            new OriginalFormatRule($registry),
            new CropTargetSize(),
            $this->encodedImageValidator,
            $this->logger
        );
    }

    /**
     * Without browser bytes the plan renders the original and encodes every variant on the server
     *
     * @return void
     */
    public function testServerRendersEverything(): void
    {
        $source = $this->source('jpeg');

        $plan = $this->planner->plan($this->input(['webp' => 85, 'avif' => 60]), $this->breakpoint(), $source);

        self::assertSame($source, $plan->getSource());
        self::assertSame([0, 0, 800, 400], [
            $plan->getRect()->getX(),
            $plan->getRect()->getY(),
            $plan->getRect()->getWidth(),
            $plan->getRect()->getHeight(),
        ]);
        self::assertSame([400, 200], [$plan->getTarget()->getWidth(), $plan->getTarget()->getHeight()]);
        self::assertSame('jpeg', $plan->getOriginal()->getCode());
        self::assertSame('jpeg', $plan->getOriginalToRender()?->getCode());
        self::assertSame(['webp', 'avif'], $this->codes($plan->getVariants()));
        self::assertSame(['webp', 'avif'], $this->codes($plan->getVariantsToEncode()));
        self::assertSame([], $plan->getSuppliedBytes());
    }

    /**
     * Valid browser bytes are kept and leave nothing for the server to produce for those formats
     *
     * @return void
     */
    public function testValidBrowserBytesAreKept(): void
    {
        $this->encodedImageValidator->method('validate')->willReturnCallback(
            fn (EncodedImage $image): ImageFormat => $this->format($image->getFormatCode())
        );

        $plan = $this->planner->plan(
            $this->input(['webp' => 85], ['png' => 'browser png', 'webp' => 'browser webp', 'gif' => 'unused']),
            $this->breakpoint(),
            $this->source('png')
        );

        self::assertSame(['png' => 'browser png', 'webp' => 'browser webp'], $plan->getSuppliedBytes());
        self::assertNull($plan->getOriginalToRender());
        self::assertSame([], $plan->getVariantsToEncode());
    }

    /**
     * Rejected browser bytes are logged, and the server encodes that format instead
     *
     * @return void
     */
    public function testRejectedBrowserBytesFallBackToTheServer(): void
    {
        $this->encodedImageValidator->method('validate')->willThrowException(
            new LocalizedException(__('The webp image cannot be decoded.'))
        );
        $this->logger->expects(self::once())->method('warning')
            ->with(self::stringContains('The webp image cannot be decoded.'));

        $plan = $this->planner->plan(
            $this->input(['webp' => 85], ['webp' => 'broken webp']),
            $this->breakpoint(),
            $this->source('png')
        );

        self::assertSame([], $plan->getSuppliedBytes());
        self::assertSame(['webp'], $this->codes($plan->getVariantsToEncode()));
    }

    /**
     * The original is JPEG for a JPEG source and PNG for every other source
     *
     * @param string $source
     * @param string $original
     * @return void
     */
    #[TestWith(['jpeg', 'jpeg'])]
    #[TestWith(['png', 'png'])]
    #[TestWith(['gif', 'png'])]
    #[TestWith(['webp', 'png'])]
    #[TestWith(['avif', 'png'])]
    public function testOriginalFormatRule(string $source, string $original): void
    {
        $plan = $this->planner->plan($this->input([]), $this->breakpoint(), $this->source($source));

        self::assertSame($original, $plan->getOriginal()->getCode());
    }

    /**
     * A requested variant in the original's own format adds nothing and is ignored
     *
     * @return void
     */
    public function testVariantInOriginalFormatIgnored(): void
    {
        $plan = $this->planner->plan(
            $this->input(['png' => 90, 'webp' => 85]),
            $this->breakpoint(),
            $this->source('png')
        );

        self::assertSame(['webp'], $this->codes($plan->getVariants()));
    }

    /**
     * A variant format without encoder and without browser bytes is refused, and so is a format that is not a
     * variant format; both are reported at once
     *
     * @return void
     */
    public function testEveryUnproducibleFormatIsReported(): void
    {
        $this->encodable = ['webp'];

        try {
            $this->planner->plan(
                $this->input(['gif' => 90, 'avif' => 60, 'webp' => 85]),
                $this->breakpoint(),
                $this->source('png')
            );
            self::fail('Formats that cannot be produced must be refused.');
        } catch (ValidationException $exception) {
            self::assertSame(
                [
                    'The crop for breakpoint "desktop" asks for the format "gif", '
                        . 'which is not an available variant format.',
                    'The crop for breakpoint "desktop" cannot be produced as avif: this server has no encoder for the '
                        . 'format. Untick the format or install an encoder.',
                ],
                array_map(
                    static fn (LocalizedException $error): string => $error->getMessage(),
                    $exception->getErrors()
                )
            );
        }
    }

    /**
     * When the browser bytes of an unencodable format were rejected, the error says why
     *
     * @return void
     */
    public function testUnencodableFormatAfterRejectedBytes(): void
    {
        $this->encodable = [];
        $this->encodedImageValidator->method('validate')->willThrowException(
            new LocalizedException(__('the bytes are 3x3 pixels'))
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('the supplied image was rejected (the bytes are 3x3 pixels)');
        $this->planner->plan(
            $this->input(['avif' => 60], ['avif' => 'bad avif']),
            $this->breakpoint(),
            $this->source('png')
        );
    }

    /**
     * An input without a crop area (only a removal has none) has nothing to cut
     *
     * @return void
     */
    public function testMissingAreaIsRefused(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The crop for breakpoint "desktop" needs a crop area.');
        $this->planner->plan(
            new CropInput(3, null, null, [], [], false, true),
            $this->breakpoint(),
            $this->source('png')
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
     * The format codes of format requests
     *
     * @param list<FormatRequest> $requests
     * @return list<string>
     */
    private function codes(array $requests): array
    {
        return array_map(static fn (FormatRequest $request): string => $request->getFormatCode(), $requests);
    }
}
