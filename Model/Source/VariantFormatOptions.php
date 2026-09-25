<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Source;

use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * The formats a crop may be encoded in as extra variants, most preferred first, as select options.
 *
 * The value is the format code; the label is the code in upper case (`AVIF`, `WEBP`), a format name that is not
 * translated.
 */
class VariantFormatOptions implements OptionSourceInterface
{
    /**
     * @param ImageFormatRegistryInterface $formatRegistry
     */
    public function __construct(
        private readonly ImageFormatRegistryInterface $formatRegistry
    ) {
    }

    /**
     * Variant format options for select and multiselect fields
     *
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->formatRegistry->getVariantFormats() as $format) {
            $options[] = ['value' => $format->getCode(), 'label' => strtoupper($format->getCode())];
        }

        return $options;
    }
}
