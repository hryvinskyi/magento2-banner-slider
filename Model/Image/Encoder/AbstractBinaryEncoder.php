<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image\Encoder;

use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Process\BinaryLocator;
use Hryvinskyi\BannerSlider\Model\Process\ProcessFailedException;
use Hryvinskyi\BannerSlider\Model\Process\ProcessRunner;

/**
 * Encodes by running a command-line encoder found by the binary locator.
 *
 * The command is `<binary> <options> -- <source>`: the `--` ends the options, so a source path can never be read as
 * one. The quality is clamped to 1..100 and the timeout comes from `di.xml`.
 */
abstract class AbstractBinaryEncoder implements ImageEncoderInterface
{
    private const MIN_QUALITY = 1;
    private const MAX_QUALITY = 100;
    private const END_OF_OPTIONS = '--';

    /**
     * @param BinaryLocator $binaryLocator
     * @param ProcessRunner $processRunner
     * @param float $timeout Seconds before the encoder is stopped
     */
    public function __construct(
        private readonly BinaryLocator $binaryLocator,
        private readonly ProcessRunner $processRunner,
        private readonly float $timeout
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->binaryLocator->locate($this->getBinaryName()) !== null;
    }

    /**
     * @inheritDoc
     */
    public function encode(string $sourceLocalPath, string $destinationLocalPath, int $quality): void
    {
        $binary = $this->binaryLocator->locate($this->getBinaryName());
        if ($binary === null) {
            throw new EncodingException(
                __('The %1 encoder is not installed on this server.', $this->getBinaryName())
            );
        }

        $command = [
            $binary,
            ...$this->getOptions(max(self::MIN_QUALITY, min(self::MAX_QUALITY, $quality)), $destinationLocalPath),
            self::END_OF_OPTIONS,
            $sourceLocalPath,
        ];
        try {
            $this->processRunner->run($command, $this->timeout);
        } catch (ProcessFailedException $e) {
            throw new EncodingException(
                __('The %1 encoder could not encode the image as %2.', $this->getBinaryName(), $this->getFormatCode()),
                $e
            );
        }
    }

    /**
     * File name of the encoder binary
     *
     * @return string
     */
    abstract protected function getBinaryName(): string;

    /**
     * The options placed between the binary and the end of options, the destination included
     *
     * @param int $quality 1..100
     * @param string $destinationLocalPath
     * @return list<string>
     */
    abstract protected function getOptions(int $quality, string $destinationLocalPath): array;
}
