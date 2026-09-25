<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Image\MediaImage;
use Hryvinskyi\BannerSliderApi\Api\Value\CropRect;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\FormatRequest;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;

/**
 * The checked output of one crop, known before anything is written: what to cut, at which size, in which formats, and
 * which of those the browser already supplied as valid bytes.
 *
 * Every format in it can be produced: a format without supplied bytes has a server encoder. Writing it can therefore
 * only fail on storage, never on validation.
 */
class CropOutputPlan
{
    /**
     * @param MediaImage $source The image the crop is cut from
     * @param CropRect $rect The area cut, in source pixels
     * @param Dimensions $target The size the crop is rendered at
     * @param ImageFormat $original Format of the original-format output
     * @param list<FormatRequest> $variants Variant formats, none in the original's format
     * @param array<string,string> $suppliedBytes Browser-encoded bytes that passed their checks, by format code
     */
    public function __construct(
        private readonly MediaImage $source,
        private readonly CropRect $rect,
        private readonly Dimensions $target,
        private readonly ImageFormat $original,
        private readonly array $variants,
        private readonly array $suppliedBytes
    ) {
    }

    /**
     * The image the crop is cut from
     *
     * @return MediaImage
     */
    public function getSource(): MediaImage
    {
        return $this->source;
    }

    /**
     * The area cut, in source pixels
     *
     * @return CropRect
     */
    public function getRect(): CropRect
    {
        return $this->rect;
    }

    /**
     * The size the crop is rendered at
     *
     * @return Dimensions
     */
    public function getTarget(): Dimensions
    {
        return $this->target;
    }

    /**
     * Format of the original-format output
     *
     * @return ImageFormat
     */
    public function getOriginal(): ImageFormat
    {
        return $this->original;
    }

    /**
     * The variant formats with their quality
     *
     * @return list<FormatRequest>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * Valid browser-encoded bytes by format code
     *
     * @return array<string,string>
     */
    public function getSuppliedBytes(): array
    {
        return $this->suppliedBytes;
    }

    /**
     * The original format when the server must render it, or null when the browser supplied it
     *
     * @return ImageFormat|null
     */
    public function getOriginalToRender(): ?ImageFormat
    {
        return isset($this->suppliedBytes[$this->original->getCode()]) ? null : $this->original;
    }

    /**
     * The variants the server must encode: those the browser did not supply
     *
     * @return list<FormatRequest>
     */
    public function getVariantsToEncode(): array
    {
        return array_values(array_filter(
            $this->variants,
            fn (FormatRequest $request): bool => !isset($this->suppliedBytes[$request->getFormatCode()])
        ));
    }
}
