<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Validation\Rule\ResponsiveCropRuleInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;

/**
 * Every variant of a crop is in a format the registry offers as a variant format.
 *
 * Whether the server can encode the format right now is not checked here: a stored variant stays valid while an
 * encoder is missing, and the crop writer reports formats it cannot produce.
 */
class VariantFormatsRegistered implements ResponsiveCropRuleInterface
{
    /**
     * @param ImageFormatRegistryInterface $formatRegistry
     */
    public function __construct(
        private readonly ImageFormatRegistryInterface $formatRegistry
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(ResponsiveCropInterface $crop): array
    {
        $variantCodes = array_map(
            static fn (ImageFormat $format): string => $format->getCode(),
            $this->formatRegistry->getVariantFormats()
        );

        $messages = [];
        foreach ($crop->getVariants() as $variant) {
            if (!in_array($variant->getFormat(), $variantCodes, true)) {
                $messages[] = __(
                    'The crop for breakpoint %1 asks for the format "%2", which is not an available variant format.',
                    $crop->getBreakpointId() ?? 0,
                    $variant->getFormat()
                );
            }
        }

        return $messages;
    }
}
