<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\FormatRequest;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;

/**
 * Writes the new file set of one crop, following its checked output plan, without touching any existing file.
 *
 * - The browser-supplied bytes of the plan are stored as they are; every other format of the plan is rendered and
 *   encoded on the server.
 * - Files are named by content hash. A file that already exists with the same bytes is reused and not reported as
 *   created; if storing fails part-way, the files this call created are removed before the error is rethrown.
 *
 * The plan has already been checked (see CropOutputPlanner), so a write fails only when a file cannot be rendered,
 * encoded or stored; never with a validation error. Inside a database transaction that keeps the rollback to storage
 * failures.
 */
class CropWriter
{
    /**
     * @param ImageFormatRegistryInterface $formatRegistry
     * @param ServerCropEncoder $serverEncoder
     * @param CropFileStore $fileStore
     * @param CropOutputFiles $cropOutputFiles
     */
    public function __construct(
        private readonly ImageFormatRegistryInterface $formatRegistry,
        private readonly ServerCropEncoder $serverEncoder,
        private readonly CropFileStore $fileStore,
        private readonly CropOutputFiles $cropOutputFiles
    ) {
    }

    /**
     * Produce and store the crop's new files
     *
     * @param ResponsiveCropInterface|null $current The stored crop the files replace, or null for a new crop
     * @param CropOutputPlan $plan The checked output of the crop
     * @param int $bannerId Id of the saved banner; crop file names contain it
     * @param string $identifier Identifier of the crop's breakpoint; crop file names contain it
     * @return CropWriteResult
     * @throws CouldNotSaveException When the crop cannot be rendered, encoded or stored
     */
    public function write(
        ?ResponsiveCropInterface $current,
        CropOutputPlan $plan,
        int $bannerId,
        string $identifier
    ): CropWriteResult {
        $bytes = $plan->getSuppliedBytes() + $this->serverBytes($plan, $identifier);

        return $this->store($current, $bannerId, $identifier, $plan->getOriginal(), $plan->getVariants(), $bytes);
    }

    /**
     * Bytes rendered and encoded on the server for the formats the browser did not supply
     *
     * @param CropOutputPlan $plan
     * @param string $identifier
     * @return array<string,string>
     * @throws CouldNotSaveException
     */
    private function serverBytes(CropOutputPlan $plan, string $identifier): array
    {
        $original = $plan->getOriginalToRender();
        $variants = $plan->getVariantsToEncode();
        if ($original === null && $variants === []) {
            return [];
        }
        try {
            return $this->serverEncoder->encode(
                $plan->getSource(),
                $plan->getRect(),
                $plan->getTarget(),
                $original,
                $variants
            );
        } catch (LocalizedException | \InvalidArgumentException $exception) {
            throw new CouldNotSaveException(
                __('The crop for breakpoint "%1" could not be generated: %2', $identifier, $exception->getMessage()),
                $exception
            );
        }
    }

    /**
     * Store every output file and describe the result
     *
     * @param ResponsiveCropInterface|null $current
     * @param int $bannerId
     * @param string $identifier
     * @param ImageFormat $original
     * @param list<FormatRequest> $variants
     * @param array<string,string> $bytes
     * @return CropWriteResult
     * @throws CouldNotSaveException
     */
    private function store(
        ?ResponsiveCropInterface $current,
        int $bannerId,
        string $identifier,
        ImageFormat $original,
        array $variants,
        array $bytes
    ): CropWriteResult {
        $created = [];
        $newPaths = [];
        $storedVariants = [];
        try {
            $file = $this->storeOne($bannerId, $identifier, $original, $bytes);
            $originalPath = $newPaths[] = $file->getPath();
            if ($file->isCreated()) {
                $created[] = $file->getPath();
            }
            foreach ($variants as $request) {
                $format = $this->formatRegistry->get($request->getFormatCode());
                $file = $this->storeOne($bannerId, $identifier, $format, $bytes);
                $newPaths[] = $file->getPath();
                if ($file->isCreated()) {
                    $created[] = $file->getPath();
                }
                $storedVariants[] = new CropVariant($format->getCode(), $request->getQuality(), $file->getPath());
            }
        } catch (\Throwable $exception) {
            $this->fileStore->discard($created);
            throw $exception;
        }
        $previous = $current === null ? [] : $this->cropOutputFiles->ofCrop($current);

        return new CropWriteResult(
            $originalPath,
            $storedVariants,
            $created,
            array_values(array_diff($previous, $newPaths))
        );
    }

    /**
     * Store the bytes of one format
     *
     * @param int $bannerId
     * @param string $identifier
     * @param ImageFormat $format
     * @param array<string,string> $bytes Bytes by format code
     * @return StoredCropFile
     * @throws CouldNotSaveException When the bytes of the format are missing or cannot be stored
     */
    private function storeOne(int $bannerId, string $identifier, ImageFormat $format, array $bytes): StoredCropFile
    {
        $content = $bytes[$format->getCode()] ?? null;
        if ($content === null) {
            throw new CouldNotSaveException(
                __('The %1 crop file for breakpoint "%2" was not produced.', $format->getCode(), $identifier)
            );
        }

        return $this->fileStore->store($bannerId, $identifier, $format, $content);
    }
}
