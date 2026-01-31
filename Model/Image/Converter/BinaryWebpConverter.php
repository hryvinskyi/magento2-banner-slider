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
 * WebP converter using cwebp command-line binary
 */
class BinaryWebpConverter implements BinaryConverterInterface
{
    private const FORMAT = 'webp';

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
            $this->logger->warning('cwebp binary is not available for WebP conversion');
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
            $process->setTimeout(120);
            $process->mustRun();

            if (!file_exists($destinationPath)) {
                $this->logger->error('WebP conversion completed but output file not found', [
                    'destination' => $destinationPath,
                ]);
                return false;
            }

            return true;
        } catch (ProcessFailedException $e) {
            $this->logger->error('cwebp conversion failed', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('WebP binary conversion error', [
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
        return $this->binaryPathResolver->isCwebpAvailable();
    }

    /**
     * @inheritDoc
     */
    public function getFormat(): string
    {
        return self::FORMAT;
    }

    /**
     * Build the cwebp command arguments
     *
     * @param string $sourcePath
     * @param string $destinationPath
     * @param int $quality
     * @return array<int, string|int>
     */
    private function buildCommand(string $sourcePath, string $destinationPath, int $quality): array
    {
        $binaryPath = $this->binaryPathResolver->getCwebpPath();

        return [
            $binaryPath,
            $sourcePath,
            '-q',
            (string)$quality,
            '-alpha_q',
            '100',
            '-z',
            '9',
            '-m',
            '6',
            '-segments',
            '4',
            '-sns',
            '80',
            '-f',
            '25',
            '-sharpness',
            '0',
            '-strong',
            '-pass',
            '10',
            '-mt',
            '-alpha_method',
            '1',
            '-alpha_filter',
            'fast',
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
