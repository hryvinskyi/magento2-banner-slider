<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSliderApi\Api\Data\CropVariantInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;

/**
 * The file set the crop writer produced for one crop, before any row points at it.
 *
 * - `originalPath` and `variants`: the new output, to be stored on the crop.
 * - `createdPaths`: only the files the write created. A file that already existed with the same bytes is not
 *   listed, so undoing a failed save never deletes a file other rows may use.
 * - `obsoletePaths`: the crop's previous output files that are not among the new ones; they may be deleted once the
 *   new rows are committed.
 */
class CropWriteResult
{
    /**
     * @param string $originalPath
     * @param list<CropVariantInterface> $variants Each with its generated path
     * @param list<string> $createdPaths
     * @param list<string> $obsoletePaths
     */
    public function __construct(
        private readonly string $originalPath,
        private readonly array $variants,
        private readonly array $createdPaths,
        private readonly array $obsoletePaths
    ) {
    }

    /**
     * Path of the new original-format output
     *
     * @return string
     */
    public function getOriginalPath(): string
    {
        return $this->originalPath;
    }

    /**
     * The new variants, each with its generated path
     *
     * @return list<CropVariantInterface>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * Files this write created
     *
     * @return list<string>
     */
    public function getCreatedPaths(): array
    {
        return $this->createdPaths;
    }

    /**
     * Every path of the new output, created or reused
     *
     * @return list<string>
     */
    public function getNewPaths(): array
    {
        $paths = [$this->originalPath];
        foreach ($this->variants as $variant) {
            $path = $variant->getPath();
            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Previous output files of the crop the new output replaces
     *
     * @return list<string>
     */
    public function getObsoletePaths(): array
    {
        return $this->obsoletePaths;
    }

    /**
     * Point the crop at the new output
     *
     * @param ResponsiveCropInterface $crop
     * @return void
     */
    public function applyTo(ResponsiveCropInterface $crop): void
    {
        $crop->setCroppedImage($this->originalPath);
        $crop->setVariants($this->variants);
    }
}
