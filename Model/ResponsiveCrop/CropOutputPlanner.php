<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\CropInput;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\FormatRequest;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;
use Psr\Log\LoggerInterface;

/**
 * Decides, before anything is written, what one crop's files will be, and refuses a crop that could not be produced.
 *
 * - The original-format output follows the original-format rule (JPEG for a JPEG source, PNG otherwise); a requested
 *   variant in that same format is ignored. Every other requested format must be a variant format.
 * - Bytes the browser encoded are checked here (see EncodedImageValidator). Valid bytes are kept; rejected ones are
 *   logged, and the server encodes that format instead.
 * - A variant format that neither valid browser bytes nor a server encoder can produce is a validation error naming
 *   the breakpoint and the format, with the reason the browser bytes were rejected, if any.
 *
 * Because every such check happens here, a crop write that follows the plan can fail only on storage.
 */
class CropOutputPlanner
{
    /**
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param OriginalFormatRule $originalFormatRule
     * @param CropTargetSize $targetSize
     * @param EncodedImageValidator $encodedImageValidator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly OriginalFormatRule $originalFormatRule,
        private readonly CropTargetSize $targetSize,
        private readonly EncodedImageValidator $encodedImageValidator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * The checked output plan of a crop
     *
     * @param CropInput $input The desired crop
     * @param BreakpointInterface $breakpoint
     * @param MediaImage $source The checked source image
     * @return CropOutputPlan
     * @throws ValidationException When the input has no area, asks for a format that is not a variant format, or asks
     *     for one that cannot be produced
     */
    public function plan(CropInput $input, BreakpointInterface $breakpoint, MediaImage $source): CropOutputPlan
    {
        $identifier = $breakpoint->getIdentifier();
        $rect = $input->getRect();
        if ($rect === null) {
            throw $this->invalid([__('The crop for breakpoint "%1" needs a crop area.', $identifier)]);
        }

        $target = $this->targetSize->resolve($breakpoint, $rect);
        $original = $this->originalFormatRule->resolve($source->getFormat());
        [$variants, $errors] = $this->variantRequests($input, $original, $identifier);
        $wanted = [$original->getCode(), ...array_map(
            static fn (FormatRequest $request): string => $request->getFormatCode(),
            $variants
        )];
        [$bytes, $rejections] = $this->browserBytes($input, $breakpoint, $target, $wanted);
        $plan = new CropOutputPlan($source, $rect, $target, $original, $variants, $bytes);
        array_push($errors, ...$this->unproducible($plan->getVariantsToEncode(), $identifier, $rejections));
        if ($errors !== []) {
            throw $this->invalid($errors);
        }

        return $plan;
    }

    /**
     * The variant requests of the input and the errors of those that are not variant formats
     *
     * A request in the original's format is left out: the original-format output covers it.
     *
     * @param CropInput $input
     * @param ImageFormat $original
     * @param string $identifier
     * @return array{0: list<FormatRequest>, 1: list<Phrase>}
     */
    private function variantRequests(CropInput $input, ImageFormat $original, string $identifier): array
    {
        $variantCodes = array_map(
            static fn (ImageFormat $format): string => $format->getCode(),
            $this->formatRegistry->getVariantFormats()
        );
        $requests = [];
        $errors = [];
        foreach ($input->getFormats() as $request) {
            $code = $request->getFormatCode();
            if ($code === $original->getCode()) {
                continue;
            }
            if (!in_array($code, $variantCodes, true)) {
                $errors[] = __(
                    'The crop for breakpoint "%1" asks for the format "%2", '
                    . 'which is not an available variant format.',
                    $identifier,
                    $code
                );
                continue;
            }
            $requests[] = $request;
        }

        return [$requests, $errors];
    }

    /**
     * The browser-encoded bytes that passed their checks, and the reason each rejected format failed
     *
     * @param CropInput $input
     * @param BreakpointInterface $breakpoint
     * @param Dimensions $target
     * @param list<string> $wanted Format codes the crop needs
     * @return array{0: array<string,string>, 1: array<string,string>} Bytes by format code, rejection by format code
     */
    private function browserBytes(
        CropInput $input,
        BreakpointInterface $breakpoint,
        Dimensions $target,
        array $wanted
    ): array {
        $bytes = [];
        $rejections = [];
        foreach ($input->getEncodedImages() as $image) {
            $code = $image->getFormatCode();
            if (!in_array($code, $wanted, true)) {
                continue;
            }
            try {
                $this->encodedImageValidator->validate($image, $breakpoint, $target);
                $bytes[$code] = $image->getBytes();
            } catch (LocalizedException $exception) {
                $rejections[$code] = $exception->getMessage();
                $this->logger->warning(
                    'Banner slider: a browser-encoded crop was rejected and is encoded on the server instead. '
                    . $exception->getMessage(),
                    ['exception' => $exception]
                );
            }
        }

        return [$bytes, $rejections];
    }

    /**
     * An error for every variant the browser did not validly supply and the server cannot encode
     *
     * @param list<FormatRequest> $toEncode Variants without valid browser bytes
     * @param string $identifier
     * @param array<string,string> $rejections Why browser bytes of a format were rejected
     * @return list<Phrase>
     */
    private function unproducible(array $toEncode, string $identifier, array $rejections): array
    {
        $errors = [];
        foreach ($toEncode as $request) {
            $code = $request->getFormatCode();
            if ($this->formatRegistry->isEncodable($this->formatRegistry->get($code))) {
                continue;
            }
            $errors[] = isset($rejections[$code])
                ? __(
                    'The crop for breakpoint "%1" cannot be produced as %2: the supplied image was rejected (%3) '
                    . 'and this server has no encoder for the format.',
                    $identifier,
                    $code,
                    $rejections[$code]
                )
                : __(
                    'The crop for breakpoint "%1" cannot be produced as %2: this server has no encoder for the format. '
                    . 'Untick the format or install an encoder.',
                    $identifier,
                    $code
                );
        }

        return $errors;
    }

    /**
     * A validation exception carrying every error
     *
     * @param non-empty-list<Phrase> $errors
     * @return ValidationException
     */
    private function invalid(array $errors): ValidationException
    {
        return new ValidationException(
            __('The crop is not valid: %1', implode(' ', array_map(
                static fn (Phrase $error): string => $error->render(),
                $errors
            ))),
            null,
            0,
            new ValidationResult($errors)
        );
    }
}
