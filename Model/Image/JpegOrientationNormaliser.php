<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use Psr\Log\LoggerInterface;

/**
 * Turns an uploaded JPEG upright when its EXIF orientation says the camera stored it rotated or mirrored.
 *
 * Browsers honour the EXIF orientation of an `<img>`, but the pixel size read from the file, the crop areas drawn on
 * it and every crop cut from it use the stored pixels. So a JPEG whose orientation is not 1 is decoded, rotated and
 * flipped upright through GD and re-encoded at the configured JPEG quality, without EXIF, before it is stored. Other
 * types, and a JPEG already upright or without an orientation, are used as they are.
 *
 * Reading EXIF needs the `exif` extension. Without it JPEGs are stored as they are, and that is logged once.
 */
class JpegOrientationNormaliser
{
    private const JPEG_MIME = 'image/jpeg';
    private const JPEG_EXTENSION = 'jpg';
    private const JPEG_FORMAT_CODE = 'jpeg';
    private const EXIF_FUNCTION = 'exif_read_data';
    private const ORIENTATION_KEY = 'Orientation';

    /**
     * How to turn each EXIF orientation upright: degrees to rotate counter-clockwise, then the GD flip mode, if any
     */
    private const CORRECTIONS = [
        2 => [0, IMG_FLIP_HORIZONTAL],
        3 => [180, null],
        4 => [0, IMG_FLIP_VERTICAL],
        5 => [-90, IMG_FLIP_HORIZONTAL],
        6 => [-90, null],
        7 => [90, IMG_FLIP_HORIZONTAL],
        8 => [90, null],
    ];

    /**
     * @var bool
     */
    private bool $missingExifLogged = false;

    /**
     * @param RuntimeCapabilities $runtime
     * @param GdImageDecoder $decoder
     * @param LocalFileWorkspace $workspace
     * @param LocalFileDriver $localDriver
     * @param ImageConfigInterface $imageConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RuntimeCapabilities $runtime,
        private readonly GdImageDecoder $decoder,
        private readonly LocalFileWorkspace $workspace,
        private readonly LocalFileDriver $localDriver,
        private readonly ImageConfigInterface $imageConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Hand the upload to `$use`, or an upright copy of it when it is a JPEG stored rotated or mirrored
     *
     * The copy is a local temp file, not an HTTP upload, with the copy's size; it is removed once `$use` returns.
     *
     * @template T
     * @param UploadedFile $file A received upload
     * @param string $mimeType The type sniffed from its content
     * @param callable(UploadedFile):T $use
     * @return T
     * @throws EncodingException When a rotated JPEG cannot be decoded or written upright
     * @throws FileSystemException When the temp copy cannot be written, read or removed
     * @throws ValidatorException
     */
    public function withUpright(UploadedFile $file, string $mimeType, callable $use): mixed
    {
        $correction = $mimeType === self::JPEG_MIME ? $this->correctionOf($file->getTemporaryPath()) : null;
        if ($correction === null) {
            return $use($file);
        }

        $upright = $this->workspace->newTempPath(self::JPEG_EXTENSION);
        try {
            $this->writeUpright($file->getTemporaryPath(), $upright, $correction[0], $correction[1]);
            $size = strlen($this->localDriver->fileGetContents($upright));

            return $use(new UploadedFile($upright, $file->getClientFileName(), $size, UPLOAD_ERR_OK, false));
        } finally {
            $this->workspace->discard($upright);
        }
    }

    /**
     * The rotation and flip that turn the JPEG upright, or null when it is upright or its orientation is unknown
     *
     * @param string $localPath
     * @return array{0: int, 1: int|null}|null
     */
    private function correctionOf(string $localPath): ?array
    {
        if (!$this->runtime->hasFunction(self::EXIF_FUNCTION)) {
            $this->logMissingExif();

            return null;
        }
        try {
            $exif = exif_read_data($localPath);
        } catch (\Throwable) {
            return null;
        }
        $orientation = is_array($exif) ? ($exif[self::ORIENTATION_KEY] ?? null) : null;

        return is_int($orientation) ? (self::CORRECTIONS[$orientation] ?? null) : null;
    }

    /**
     * Decode the JPEG, turn it upright and write it as a new JPEG without EXIF
     *
     * @param string $sourcePath
     * @param string $uprightPath
     * @param int $degrees Counter-clockwise rotation
     * @param int|null $flipMode GD flip mode applied after the rotation
     * @return void
     * @throws EncodingException
     */
    private function writeUpright(string $sourcePath, string $uprightPath, int $degrees, ?int $flipMode): void
    {
        try {
            $image = $this->decoder->decode($this->localDriver->fileGetContents($sourcePath));
        } catch (FileSystemException $exception) {
            throw new EncodingException(__('The uploaded image could not be read.'), $exception);
        }

        try {
            if ($degrees !== 0) {
                $image = imagerotate($image, $degrees, 0);
            }
            $written = $image !== false
                && ($flipMode === null || imageflip($image, $flipMode))
                && imagejpeg($image, $uprightPath, $this->imageConfig->getDefaultQuality(self::JPEG_FORMAT_CODE));
        } catch (\Throwable $exception) {
            throw new EncodingException(
                __('The uploaded image could not be turned upright.'),
                $exception instanceof \Exception ? $exception : null
            );
        }
        if (!$written) {
            throw new EncodingException(__('The uploaded image could not be turned upright.'));
        }
    }

    /**
     * Log, once per instance, that JPEG orientation cannot be read on this runtime
     *
     * @return void
     */
    private function logMissingExif(): void
    {
        if ($this->missingExifLogged) {
            return;
        }
        $this->missingExifLogged = true;
        $this->logger->warning(
            'Banner slider: the PHP exif extension is not loaded, so uploaded JPEGs are stored without being turned '
            . 'upright by their EXIF orientation.'
        );
    }
}
