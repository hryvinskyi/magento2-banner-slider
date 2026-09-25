<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Banner;

use Hryvinskyi\BannerSlider\Model\Image\MediaImageReader;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Magento\Framework\Exception\LocalizedException;

/**
 * The pixel size a banner's image is stored with, always read from the file and never taken from a request.
 *
 * - No image: no size.
 * - The stored image, already sized: the stored size.
 * - A new image, or a stored one without a size: the size read from the file. A new image that cannot be read is an
 *   error; a stored image that cannot be read keeps no size, so a banner whose file went missing can still be saved.
 */
class BannerImageSizer
{
    /**
     * @param MediaImageReader $imageReader
     */
    public function __construct(
        private readonly MediaImageReader $imageReader
    ) {
    }

    /**
     * The size to store with the banner's image
     *
     * @param BannerInterface $banner The banner as it will be saved
     * @param BannerInterface|null $stored The banner as it is stored, or null for a new banner
     * @return Dimensions|null
     * @throws LocalizedException When a new image is not a readable image
     */
    public function resolve(BannerInterface $banner, ?BannerInterface $stored): ?Dimensions
    {
        $image = $banner->getImage();
        if ($image === null) {
            return null;
        }
        $unchanged = $stored !== null && $stored->getImage() === $image;
        $storedSize = $unchanged ? $stored->getImageDimensions() : null;
        if ($storedSize !== null) {
            return $storedSize;
        }

        try {
            return $this->imageReader->read($image)->getDimensions();
        } catch (LocalizedException $exception) {
            if ($unchanged) {
                return null;
            }
            throw new LocalizedException(
                __('The banner image "%1" cannot be used: %2', $image, $exception->getMessage()),
                $exception
            );
        }
    }
}
