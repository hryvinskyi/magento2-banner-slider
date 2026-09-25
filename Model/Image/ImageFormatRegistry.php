<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;

/**
 * The image formats configured in `di.xml`, keyed by format code.
 *
 * Each entry declares `mime`, `extension` (canonical, used for new files), optional `aliases` (further extensions of
 * existing files, such as `jpeg` for `jpg`), `variant` (whether a crop may be encoded in it as an extra format) and
 * `preference` (higher first among the variant formats). A broken entry, or an extension claimed by two formats,
 * fails loudly when the registry is built. Whether a format can be encoded right now is the image converter's answer.
 */
class ImageFormatRegistry implements ImageFormatRegistryInterface
{
    /**
     * @var array<string,ImageFormat>
     */
    private readonly array $formats;

    /**
     * @var array<string,string> Lowercase extension or alias => format code
     */
    private readonly array $codesByExtension;

    /**
     * @var list<string> Variant format codes, most preferred first
     */
    private readonly array $variantCodes;

    /**
     * @param ImageConverter $imageConverter
     * @param array<string,array<string,mixed>> $formats Format definitions keyed by format code
     * @throws \InvalidArgumentException When a definition is incomplete or two formats claim one extension
     */
    public function __construct(
        private readonly ImageConverter $imageConverter,
        array $formats = []
    ) {
        $definitions = [];
        $codesByExtension = [];
        $preferences = [];
        foreach ($formats as $code => $definition) {
            $format = new ImageFormat(
                $code,
                $this->requireString($definition, 'mime', $code),
                $this->requireString($definition, 'extension', $code)
            );
            $definitions[$format->getCode()] = $format;
            foreach ([$format->getExtension(), ...$this->aliases($definition, $format->getCode())] as $extension) {
                $claimedBy = $codesByExtension[$extension] ?? $format->getCode();
                if ($claimedBy !== $format->getCode()) {
                    throw new \InvalidArgumentException(sprintf(
                        'The extension "%s" is claimed by the image formats "%s" and "%s".',
                        $extension,
                        $claimedBy,
                        $format->getCode()
                    ));
                }
                $codesByExtension[$extension] = $format->getCode();
            }
            if (($definition['variant'] ?? false) === true) {
                $preferences[$format->getCode()] = $this->preference($definition, $format->getCode());
            }
        }
        uksort(
            $preferences,
            static fn (string $a, string $b): int => [$preferences[$b], $a] <=> [$preferences[$a], $b]
        );

        $this->formats = $definitions;
        $this->codesByExtension = $codesByExtension;
        $this->variantCodes = array_keys($preferences);
    }

    /**
     * @inheritDoc
     */
    public function get(string $code): ImageFormat
    {
        if (!isset($this->formats[$code])) {
            throw new \InvalidArgumentException(sprintf('The image format "%s" is not registered.', $code));
        }

        return $this->formats[$code];
    }

    /**
     * @inheritDoc
     */
    public function has(string $code): bool
    {
        return isset($this->formats[$code]);
    }

    /**
     * @inheritDoc
     */
    public function getByMimeType(string $mimeType): ?ImageFormat
    {
        $mimeType = strtolower(trim($mimeType));
        foreach ($this->formats as $format) {
            if ($format->getMimeType() === $mimeType) {
                return $format;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getByExtension(string $extension): ?ImageFormat
    {
        $code = $this->codesByExtension[strtolower(ltrim(trim($extension), '.'))] ?? null;

        return $code === null ? null : $this->formats[$code];
    }

    /**
     * @inheritDoc
     */
    public function getVariantFormats(): array
    {
        return array_map(fn (string $code): ImageFormat => $this->formats[$code], $this->variantCodes);
    }

    /**
     * @inheritDoc
     */
    public function isEncodable(ImageFormat $format): bool
    {
        return $this->imageConverter->isEncodable($format);
    }

    /**
     * A required string entry of a format definition
     *
     * @param array<string,mixed> $definition
     * @param string $key
     * @param string $code
     * @return string
     * @throws \InvalidArgumentException When the entry is missing or not a non-empty string
     */
    private function requireString(array $definition, string $key, string $code): string
    {
        $value = $definition[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException(
                sprintf('The image format "%s" needs a non-empty "%s" entry.', $code, $key)
            );
        }

        return strtolower($value);
    }

    /**
     * The alias extensions of a format definition, lowercased
     *
     * @param array<string,mixed> $definition
     * @param string $code
     * @return list<string>
     * @throws \InvalidArgumentException When an alias is not a non-empty string
     */
    private function aliases(array $definition, string $code): array
    {
        $aliases = $definition['aliases'] ?? [];
        if (!is_array($aliases)) {
            throw new \InvalidArgumentException(sprintf('The aliases of image format "%s" must be a list.', $code));
        }

        $result = [];
        foreach ($aliases as $alias) {
            if (!is_string($alias) || $alias === '') {
                throw new \InvalidArgumentException(
                    sprintf('The aliases of image format "%s" must be non-empty strings.', $code)
                );
            }
            $result[] = strtolower($alias);
        }

        return $result;
    }

    /**
     * The preference of a variant format definition
     *
     * @param array<string,mixed> $definition
     * @param string $code
     * @return int
     * @throws \InvalidArgumentException When the preference is not a whole number
     */
    private function preference(array $definition, string $code): int
    {
        $preference = $definition['preference'] ?? 0;
        if (is_int($preference)) {
            return $preference;
        }
        if (is_string($preference) && preg_match('/^-?\d+$/', $preference) === 1) {
            return (int)$preference;
        }

        throw new \InvalidArgumentException(
            sprintf('The preference of image format "%s" must be a whole number.', $code)
        );
    }
}
