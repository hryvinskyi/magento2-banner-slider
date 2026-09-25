<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSlider\Model\Data\MediaRelativePath;
use Hryvinskyi\BannerSliderApi\Api\Data\CropVariantInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;

/**
 * One extra image format a crop is wanted in, valid by construction and immutable.
 *
 * The format code follows the image format code rule, the quality is 1..100 and the path, once generated, is
 * relative to the media directory. Whether the format is one the registry knows is checked where crops are written.
 * Built with named arguments (`format`, `quality`, `path`), which the generated factory of the interface passes.
 */
class CropVariant implements CropVariantInterface
{
    public const MIN_QUALITY = 1;
    public const MAX_QUALITY = 100;

    /**
     * @var string|null
     */
    private readonly ?string $path;

    /**
     * @param string $format Format code, such as `webp`
     * @param int $quality Encoding quality, 1..100
     * @param string|null $path Generated file path relative to the media directory, or null while not generated
     * @throws \InvalidArgumentException When a value breaks its rule
     */
    public function __construct(
        private readonly string $format,
        private readonly int $quality,
        ?string $path = null
    ) {
        if (preg_match(ImageFormat::CODE_PATTERN, $format) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('Crop variant format must be 2-16 lowercase letters or digits, got "%s".', $format)
            );
        }
        if ($quality < self::MIN_QUALITY || $quality > self::MAX_QUALITY) {
            throw new \InvalidArgumentException(sprintf(
                'Crop variant quality must be between %d and %d, got %d.',
                self::MIN_QUALITY,
                self::MAX_QUALITY,
                $quality
            ));
        }
        $this->path = $path === null ? null : (new MediaRelativePath($path))->toString();
    }

    /**
     * @inheritDoc
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @inheritDoc
     */
    public function getQuality(): int
    {
        return $this->quality;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function withPath(?string $path): CropVariantInterface
    {
        return new self($this->format, $this->quality, $path);
    }
}
