<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSlider\Model\Media\ObsoleteMediaRemover;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\WholeImageCropArea;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCrop\CropRegeneratorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\NonTransactionableInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores the image facts earlier versions only read from disk while rendering, and turns their "show the source as it
 * is" crops into real crops.
 *
 * - **Banner image size:** every banner with an image and no stored size gets the pixel size read from the file.
 *   Without it the storefront renders such banners without `width`/`height`, so the page shifts while it loads.
 * - **Whole-image crops:** earlier versions stored "show the source image as it is" in two ways: an area of all
 *   zeros, or an output file that is the crop's source itself (see WholeImageCropArea).
 *   - When that source is the banner image (the crop has no source of its own, or its own is the banner image), the
 *     row is not a crop: it is deleted, and the storefront shows the banner image whole, as earlier versions did.
 *     Its output file is the banner image, which is never deleted; files only its variants used go through
 *     ObsoleteMediaRemover.
 *   - When the crop has a dedicated source, it gets the whole-image area of that source for its breakpoint and is
 *     then regenerated, so its output has the size the storefront reserves for it. An area drawn by hand is left
 *     alone.
 *
 * The row changes are committed in one transaction of the patch's own. Regeneration runs after that commit, one crop
 * at a time in its own transactions: a crop that cannot be regenerated rolls back alone and is logged with its id, and
 * it never fails the upgrade (a failed nested transaction inside the setup transaction would). That is why the patch
 * is not transactional. A re-run after a crash is harmless: a regenerated crop no longer looks like a whole-image
 * crop, and the others get the same area again.
 *
 * Files are read through a local copy, so remote media storage works. A file that is missing or not a readable image
 * is logged with the banner or crop id and skipped: a missing media file never stops the upgrade. Such a crop keeps
 * its earlier file until its source is copied into media and `banner-slider:crops:regenerate` is run.
 */
class BackfillLegacyMedia implements DataPatchInterface, NonTransactionableInterface
{
    private const BANNER_TABLE = 'hryvinskyi_banner_slider_banner';
    private const CROP_TABLE = 'hryvinskyi_banner_slider_responsive_crop';
    private const BREAKPOINT_TABLE = 'hryvinskyi_banner_slider_breakpoint';
    private const VARIANT_TABLE = 'hryvinskyi_banner_slider_crop_variant';
    private const EMPTY_AREA = 'empty area';
    private const SOURCE_AS_OUTPUT = 'source as output';

    /**
     * Sizes read in this run, by media path; null when the file could not be read
     *
     * @var array<string, Dimensions|null>
     */
    private array $sizes = [];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param TypedRowFetcher $rowFetcher
     * @param LocalFileWorkspace $workspace
     * @param ImageInspector $imageInspector
     * @param WholeImageCropArea $wholeImageCropArea
     * @param ObsoleteMediaRemover $obsoleteMediaRemover
     * @param CropRegeneratorInterface $cropRegenerator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly TypedRowFetcher $rowFetcher,
        private readonly LocalFileWorkspace $workspace,
        private readonly ImageInspector $imageInspector,
        private readonly WholeImageCropArea $wholeImageCropArea,
        private readonly ObsoleteMediaRemover $obsoleteMediaRemover,
        private readonly CropRegeneratorInterface $cropRegenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $this->sizes = [];

        $connection->beginTransaction();
        try {
            $this->backfillBannerImageSizes($connection);
            [$releasedFiles, $toRegenerate] = $this->resolveWholeImageCrops($connection);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $this->obsoleteMediaRemover->remove($releasedFiles);
        $this->regenerate($toRegenerate);

        return $this;
    }

    /**
     * Store the size of every banner image that has none
     *
     * @param AdapterInterface $connection
     * @return void
     */
    private function backfillBannerImageSizes(AdapterInterface $connection): void
    {
        $bannerTable = $this->moduleDataSetup->getTable(self::BANNER_TABLE);
        $images = $this->rowFetcher->fetchIdValuePairs(
            $connection,
            $connection->select()
                ->from($bannerTable, ['banner_id', 'image'])
                ->where("image IS NOT NULL AND image <> ''")
                ->where('image_width IS NULL OR image_height IS NULL')
        );

        $filled = 0;
        $unreadable = [];
        foreach ($images as $bannerId => $image) {
            $size = $image === null ? null : $this->sizeOf($image);
            if ($size === null) {
                $unreadable[$bannerId] = $image;
                continue;
            }
            $connection->update(
                $bannerTable,
                ['image_width' => $size->getWidth(), 'image_height' => $size->getHeight()],
                ['banner_id = ?' => $bannerId]
            );
            $filled++;
        }

        $this->logger->info(sprintf('Banner slider migration: %d banner images got their pixel size.', $filled));
        if ($unreadable !== []) {
            $this->logger->warning(
                'Banner slider migration: banner images that could not be read keep no size.',
                ['images_by_banner_id' => $unreadable]
            );
        }
    }

    /**
     * Delete the whole-image crops of the banner image, and give those of a dedicated source its whole-image area
     *
     * @param AdapterInterface $connection
     * @return array{0: list<string>, 1: array<int, array{0: int, 1: int}>} The files the deleted rows used, and
     *     the crops to regenerate as crop id => [banner id, breakpoint id]
     */
    private function resolveWholeImageCrops(AdapterInterface $connection): array
    {
        $cropTable = $this->moduleDataSetup->getTable(self::CROP_TABLE);
        $filled = [self::EMPTY_AREA => 0, self::SOURCE_AS_OUTPUT => 0];
        $bannerImageCrops = [];
        $releasedFiles = [];
        $toRegenerate = [];
        $unreadable = [];
        $withoutTarget = [];
        foreach ($this->fetchCropRows($connection) as $row) {
            $case = $this->wholeImageCase($row);
            if ($case === null) {
                continue;
            }
            $cropId = (int)$row['crop_id'];
            $source = $this->nonBlank($row['source_image'] ?? null);
            if ($source === null || $source === $this->nonBlank($row['banner_image'] ?? null)) {
                $bannerImageCrops[] = $cropId;
                $releasedFiles[] = $this->nonBlank($row['cropped_image'] ?? null);
                continue;
            }
            try {
                $spec = $this->breakpointSpec($row);
            } catch (\InvalidArgumentException $exception) {
                $withoutTarget[$cropId] = $exception->getMessage();
                continue;
            }
            $size = $this->sizeOf($source);
            if ($size === null) {
                $unreadable[$cropId] = $source;
                continue;
            }
            $area = $this->wholeImageCropArea->coverArea($size, $spec);
            $connection->update(
                $cropTable,
                [
                    'crop_x' => $area->getX(),
                    'crop_y' => $area->getY(),
                    'crop_width' => $area->getWidth(),
                    'crop_height' => $area->getHeight(),
                ],
                ['crop_id = ?' => $cropId]
            );
            $filled[$case]++;
            $toRegenerate[$cropId] = [(int)$row['banner_id'], (int)$row['breakpoint_id']];
        }
        $releasedFiles = array_merge($releasedFiles, $this->deleteCrops($connection, $bannerImageCrops));

        $this->logger->info(sprintf(
            'Banner slider migration: %d crops that showed the banner image as it is were removed; the banner image '
            . 'shows instead. %d crops with an empty area and %d crops whose output was their own source image got '
            . 'the largest centred area of that source with their breakpoint\'s aspect ratio.',
            count($bannerImageCrops),
            $filled[self::EMPTY_AREA],
            $filled[self::SOURCE_AS_OUTPUT]
        ));
        if ($bannerImageCrops !== []) {
            $this->logger->info(
                'Banner slider migration: removed crops that showed the banner image as it is.',
                ['crop_ids' => $bannerImageCrops]
            );
        }
        if ($unreadable !== []) {
            $this->logger->warning(
                'Banner slider migration: whole-image crops whose source could not be read keep their stored area '
                . 'and file; copy the source into media and run banner-slider:crops:regenerate.',
                ['sources_by_crop_id' => $unreadable]
            );
        }
        if ($withoutTarget !== []) {
            $this->logger->warning(
                'Banner slider migration: whole-image crops whose breakpoint has no valid size keep their '
                . 'stored area.',
                ['reasons_by_crop_id' => $withoutTarget]
            );
        }

        return [array_values(array_filter($releasedFiles, fn (?string $path): bool => $path !== null)), $toRegenerate];
    }

    /**
     * Every crop row with its banner image and its breakpoint's size
     *
     * @param AdapterInterface $connection
     * @return list<array<string, string|null>>
     */
    private function fetchCropRows(AdapterInterface $connection): array
    {
        return $this->rowFetcher->fetchRows(
            $connection,
            $connection->select()
                ->from(
                    ['crop' => $this->moduleDataSetup->getTable(self::CROP_TABLE)],
                    [
                        'crop_id',
                        'banner_id',
                        'breakpoint_id',
                        'source_image',
                        'cropped_image',
                        'crop_x',
                        'crop_y',
                        'crop_width',
                        'crop_height',
                    ]
                )
                ->joinLeft(
                    ['banner' => $this->moduleDataSetup->getTable(self::BANNER_TABLE)],
                    'banner.banner_id = crop.banner_id',
                    ['banner_image' => 'image']
                )
                ->join(
                    ['breakpoint' => $this->moduleDataSetup->getTable(self::BREAKPOINT_TABLE)],
                    'breakpoint.breakpoint_id = crop.breakpoint_id',
                    ['identifier', 'media_query', 'min_width', 'target_width', 'target_height']
                )
        );
    }

    /**
     * Delete crop rows (their variant rows go with them) and return the variant files they used
     *
     * @param AdapterInterface $connection
     * @param list<int> $cropIds
     * @return list<string|null>
     */
    private function deleteCrops(AdapterInterface $connection, array $cropIds): array
    {
        if ($cropIds === []) {
            return [];
        }
        $variantFiles = $this->rowFetcher->fetchIdValuePairs(
            $connection,
            $connection->select()
                ->from($this->moduleDataSetup->getTable(self::VARIANT_TABLE), ['variant_id', 'path'])
                ->where('crop_id IN (?)', $cropIds)
                ->where("path IS NOT NULL AND path <> ''")
        );
        $connection->delete($this->moduleDataSetup->getTable(self::CROP_TABLE), ['crop_id IN (?)' => $cropIds]);

        return array_values($variantFiles);
    }

    /**
     * Regenerate the crops that got a whole-image area, one at a time; failures are logged by crop id
     *
     * @param array<int,array{int,int}> $crops Crop id => [banner id, breakpoint id]
     * @return void
     */
    private function regenerate(array $crops): void
    {
        $regenerated = 0;
        $failures = [];
        foreach ($crops as $cropId => [$bannerId, $breakpointId]) {
            try {
                $regenerated += $this->cropRegenerator->regenerate($bannerId, $breakpointId);
            } catch (\Exception $exception) {
                $failures[$cropId] = $exception->getMessage();
            }
        }

        $this->logger->info(sprintf(
            'Banner slider migration: %d of %d whole-image crops with a dedicated source were regenerated.',
            $regenerated,
            count($crops)
        ));
        if ($failures !== []) {
            $this->logger->warning(
                'Banner slider migration: whole-image crops that could not be regenerated keep their earlier file; '
                . 'fix the cause and run banner-slider:crops:regenerate.',
                ['reasons_by_crop_id' => $failures]
            );
        }
    }

    /**
     * Which way a crop row stored "show the source as it is", or null when its area was chosen by hand
     *
     * @param array<string,string|null> $row
     * @return string|null
     */
    private function wholeImageCase(array $row): ?string
    {
        $area = [$row['crop_x'] ?? 0, $row['crop_y'] ?? 0, $row['crop_width'] ?? 0, $row['crop_height'] ?? 0];
        if (array_map('intval', $area) === [0, 0, 0, 0]) {
            return self::EMPTY_AREA;
        }
        $isSourceAsOutput = $this->wholeImageCropArea->isSourceAsOutput(
            $row['cropped_image'] ?? null,
            $row['source_image'] ?? null,
            $row['banner_image'] ?? null
        );

        return $isSourceAsOutput ? self::SOURCE_AS_OUTPUT : null;
    }

    /**
     * The rendering view of the crop's breakpoint row; a target height of NULL or 0 leaves the height open
     *
     * @param array<string,string|null> $row
     * @return BreakpointSpec
     * @throws \InvalidArgumentException When the row has no valid target width
     */
    private function breakpointSpec(array $row): BreakpointSpec
    {
        $targetHeight = (int)($row['target_height'] ?? 0);

        return new BreakpointSpec(
            $this->nonBlank($row['identifier'] ?? null) ?? 'breakpoint-' . (int)($row['breakpoint_id'] ?? 0),
            (string)($row['media_query'] ?? ''),
            (int)($row['min_width'] ?? 0),
            (int)($row['target_width'] ?? 0),
            $targetHeight > 0 ? $targetHeight : null
        );
    }

    /**
     * Pixel size of a media image, or null when it is missing or not a readable image
     *
     * @param string $mediaPath
     * @return Dimensions|null
     */
    private function sizeOf(string $mediaPath): ?Dimensions
    {
        if (array_key_exists($mediaPath, $this->sizes)) {
            return $this->sizes[$mediaPath];
        }

        try {
            $info = $this->workspace->withLocalCopy(
                $mediaPath,
                fn (string $localPath): ?array => $this->imageInspector->inspect($localPath)
            );
            $size = $info === null ? null : new Dimensions($info['width'], $info['height']);
        } catch (LocalizedException | \InvalidArgumentException $exception) {
            $this->logger->warning(
                sprintf('Banner slider migration: the media file "%s" cannot be read.', $mediaPath),
                ['exception' => $exception]
            );
            $size = null;
        }

        return $this->sizes[$mediaPath] = $size;
    }

    /**
     * The value, or null when it is null or blank
     *
     * @param string|null $value
     * @return string|null
     */
    private function nonBlank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [NormaliseLegacyRows::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
