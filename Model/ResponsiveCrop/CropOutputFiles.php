<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;

/**
 * The files a crop produced: its original-format output and the path of every generated variant.
 *
 * Crops of other origins may store their source image as their output; which of these paths may really be deleted is
 * decided where files are removed, not here.
 */
class CropOutputFiles
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Output paths of one crop, without duplicates
     *
     * @param ResponsiveCropInterface $crop
     * @return list<string>
     */
    public function ofCrop(ResponsiveCropInterface $crop): array
    {
        $paths = [$crop->getCroppedImage()];
        foreach ($crop->getVariants() as $variant) {
            $paths[] = $variant->getPath();
        }

        return array_values(array_unique(array_filter($paths, fn (?string $path): bool => $path !== null)));
    }

    /**
     * Output paths of every crop made for the breakpoints, read in one query (plus one for the variants)
     *
     * @param list<int> $breakpointIds
     * @return list<string>
     */
    public function ofBreakpoints(array $breakpointIds): array
    {
        if ($breakpointIds === []) {
            return [];
        }

        $paths = [];
        foreach ($this->collectionFactory->create()->addBreakpointIdsFilter($breakpointIds)->getItems() as $crop) {
            if ($crop instanceof ResponsiveCropInterface) {
                array_push($paths, ...$this->ofCrop($crop));
            }
        }

        return array_values(array_unique($paths));
    }
}
