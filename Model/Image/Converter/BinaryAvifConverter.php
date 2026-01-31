<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Converter;

use Hryvinskyi\BannerSliderApi\Api\Image\BinaryConverterInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\BinaryPathResolverInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * AVIF converter using cavif command-line binary
 */
class BinaryAvifConverter implements BinaryConverterInterface
{
    private const FORMAT = 'avif';

    /**
     * @param BinaryPathResolverInterface $binaryPathResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly BinaryPathResolverInterface $binaryPathResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function convert(string $sourcePath, string $destinationPath, int $quality): bool
    {
        if (!$this->isAvailable()) {
            $this->logger->warning('cavif binary is not available for AVIF conversion');
            return false;
        }

        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            $this->logger->error('Source image does not exist or is not readable', ['path' => $sourcePath]);
            return false;
        }

        $quality = max(1, min(100, $quality));

        try {
            $this->ensureDirectoryExists(dirname($destinationPath));

            $command = $this->buildCommand($sourcePath, $destinationPath, $quality);
            $process = new Process($command);
            $process->setTimeout(180);
            $process->mustRun();

            if (!file_exists($destinationPath)) {
                $this->logger->error('AVIF conversion completed but output file not found', [
                    'destination' => $destinationPath,
                ]);
                return false;
            }

            return true;
        } catch (ProcessFailedException $e) {
            $this->logger->error('cavif conversion failed', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('AVIF binary conversion error', [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->binaryPathResolver->isCavifAvailable();
    }

    /**
     * @inheritDoc
     */
    public function getFormat(): string
    {
        return self::FORMAT;
    }

    /**
     * Build the cavif command arguments
     *
     * @param string $sourcePath
     * @param string $destinationPath
     * @param int $quality
     * @return array<int, string>
     */
    private function buildCommand(string $sourcePath, string $destinationPath, int $quality): array
    {
        $binaryPath = $this->binaryPathResolver->getCavifPath();

        return [
            $binaryPath,
            $sourcePath,
            '-Q',
            (string)$quality,
            '-o',
            $destinationPath,
        ];
    }

    /**
     * Ensure directory exists
     *
     * @param string $directory
     * @return void
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            mkdir($directory, 0775, true);
        }
    }
}
