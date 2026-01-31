<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSliderApi\Api\Image\BinaryPathResolverInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

/**
 * Resolves paths to image processing binaries stored in vendor/bin/
 */
class BinaryPathResolver implements BinaryPathResolverInterface
{
    private const BINARY_CWEBP = 'cwebp';
    private const BINARY_CAVIF = 'cavif';

    /**
     * @var array<string, bool|null>
     */
    private array $availabilityCache = [];

    /**
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly DirectoryList $directoryList
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCwebpPath(): ?string
    {
        return $this->getBinaryPath(self::BINARY_CWEBP);
    }

    /**
     * @inheritDoc
     */
    public function getCavifPath(): ?string
    {
        return $this->getBinaryPath(self::BINARY_CAVIF);
    }

    /**
     * @inheritDoc
     */
    public function isCwebpAvailable(): bool
    {
        return $this->isBinaryAvailable(self::BINARY_CWEBP);
    }

    /**
     * @inheritDoc
     */
    public function isCavifAvailable(): bool
    {
        return $this->isBinaryAvailable(self::BINARY_CAVIF);
    }

    /**
     * @inheritDoc
     */
    public function getBinDirectory(): string
    {
        return $this->directoryList->getRoot() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
    }

    /**
     * Get full path to a binary
     *
     * @param string $binaryName
     * @return string|null
     */
    private function getBinaryPath(string $binaryName): ?string
    {
        if (!$this->isBinaryAvailable($binaryName)) {
            return null;
        }

        return $this->getBinDirectory() . DIRECTORY_SEPARATOR . $binaryName;
    }

    /**
     * Check if a binary is available and executable
     *
     * @param string $binaryName
     * @return bool
     */
    private function isBinaryAvailable(string $binaryName): bool
    {
        if (isset($this->availabilityCache[$binaryName])) {
            return $this->availabilityCache[$binaryName];
        }

        $path = $this->getBinDirectory() . DIRECTORY_SEPARATOR . $binaryName;
        $isAvailable = file_exists($path) && is_executable($path);

        $this->availabilityCache[$binaryName] = $isAvailable;

        return $isAvailable;
    }
}
