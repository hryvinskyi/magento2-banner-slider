<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use Magento\Framework\Phrase;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;

/**
 * Decides whether a received file may be stored, and what it really is.
 *
 * Nothing the client sent is trusted: the size is read from the file itself, the type is sniffed from its content
 * and the stored extension follows the sniffed type. SVG is refused whatever the allow-list says, because it can
 * carry script. Error messages never contain a server path.
 */
class UploadedFileValidator
{
    private const SCRIPTABLE_TYPES = ['image/svg+xml'];
    private const BYTES_PER_MB = 1048576;

    /**
     * @param UploadedFileChecker $uploadedFileChecker
     * @param LocalFileDriver $localDriver
     */
    public function __construct(
        private readonly UploadedFileChecker $uploadedFileChecker,
        private readonly LocalFileDriver $localDriver
    ) {
    }

    /**
     * Validate a received file against an allow-list of types and a size cap
     *
     * @param UploadedFile $file
     * @param array<string,string> $allowedTypes Accepted MIME type => extension a stored file of that type gets
     * @param int $maxBytes Largest accepted size in bytes
     * @return array{mime: string, extension: string, size: int} The sniffed type, its extension and the real size
     * @throws ValidationException When the file must not be stored
     */
    public function validate(UploadedFile $file, array $allowedTypes, int $maxBytes): array
    {
        if (!$file->isReceived()) {
            throw $this->invalid($this->uploadErrorMessage($file->getErrorCode()));
        }
        $path = $file->getTemporaryPath();
        if ($file->isHttpUpload() && !$this->uploadedFileChecker->isUploadedFile($path)) {
            throw $this->invalid(__('The file was not received as an upload.'));
        }

        $size = $this->sizeOf($path);
        if ($size === 0) {
            throw $this->invalid(__('The uploaded file is empty.'));
        }
        if ($size > $maxBytes) {
            throw $this->invalid(__(
                'The file is %1 MB, larger than the %2 MB the store accepts.',
                $this->megabytes($size),
                $this->megabytes($maxBytes)
            ));
        }

        $mime = $this->sniff($path);
        if ($mime === null || in_array($mime, self::SCRIPTABLE_TYPES, true) || !isset($allowedTypes[$mime])) {
            throw $this->invalid(__(
                'The file type is not accepted. Accepted types: %1.',
                implode(', ', array_unique(array_values($allowedTypes)))
            ));
        }

        return ['mime' => $mime, 'extension' => $allowedTypes[$mime], 'size' => $size];
    }

    /**
     * The size of the received file in bytes
     *
     * @param string $path
     * @return int
     * @throws ValidationException When the file cannot be read
     */
    private function sizeOf(string $path): int
    {
        try {
            $size = $this->localDriver->stat($path)['size'] ?? null;
        } catch (FileSystemException $exception) {
            throw $this->invalid(__('The uploaded file cannot be read.'), $exception);
        }
        if (!is_int($size)) {
            throw $this->invalid(__('The uploaded file cannot be read.'));
        }

        return $size;
    }

    /**
     * The MIME type sniffed from the file content, or null when it cannot be told
     *
     * @param string $path
     * @return string|null
     */
    private function sniff(string $path): ?string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
    }

    /**
     * The message for a failed PHP upload
     *
     * @param int $errorCode
     * @return Phrase
     */
    private function uploadErrorMessage(int $errorCode): Phrase
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('The file is larger than the server accepts.'),
            UPLOAD_ERR_PARTIAL => __('The file was only partly uploaded. Please try again.'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.'),
            default => __('The file could not be uploaded (error code %1).', $errorCode),
        };
    }

    /**
     * A byte count in megabytes with one decimal
     *
     * @param int $bytes
     * @return string
     */
    private function megabytes(int $bytes): string
    {
        return sprintf('%.1F', $bytes / self::BYTES_PER_MB);
    }

    /**
     * A validation failure carrying one message
     *
     * @param Phrase $message
     * @param \Exception|null $cause
     * @return ValidationException
     */
    private function invalid(Phrase $message, ?\Exception $cause = null): ValidationException
    {
        return new ValidationException($message, $cause, 0, new ValidationResult([$message]));
    }
}
