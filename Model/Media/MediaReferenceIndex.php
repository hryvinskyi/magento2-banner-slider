<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyMediaPathNormaliser;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\VariantRows;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\CropVariantInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * The media files the slider data still points at, read in one query per table into a snapshot.
 *
 * Counted as references:
 * - banner `image` and `video_path`, crop `source_image` and `cropped_image`, and variant `path`, each read the way
 *   the storefront reads it (a bare legacy video file name lives in the video folder, a leading `/` or an absolute
 *   media URL is reduced to the media-relative path);
 * - paths quoted in banner `content` and slider `custom_css`: media directives and media URLs.
 *
 * A caller takes one snapshot per operation and asks it about every path, so no path costs a query. The snapshot keys
 * every path by its canonical form, so a stored value spelled with doubled slashes or `.` segments still protects its
 * file.
 */
class MediaReferenceIndex
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param LegacyMediaPathNormaliser $normaliser
     * @param TextMediaReferenceExtractor $textExtractor
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LegacyMediaPathNormaliser $normaliser,
        private readonly TextMediaReferenceExtractor $textExtractor
    ) {
    }

    /**
     * Read every reference now
     *
     * @return MediaReferenceSnapshot
     */
    public function snapshot(): MediaReferenceSnapshot
    {
        $paths = [];
        foreach ($this->rows(BannerResource::TABLE_NAME, [
            BannerInterface::IMAGE,
            BannerInterface::VIDEO_PATH,
            BannerInterface::CONTENT,
        ]) as $row) {
            $paths[] = $this->image($row[BannerInterface::IMAGE]);
            $paths[] = $this->video($row[BannerInterface::VIDEO_PATH]);
            array_push($paths, ...$this->text($row[BannerInterface::CONTENT]));
        }
        foreach ($this->rows(ResponsiveCropResource::TABLE_NAME, [
            ResponsiveCropInterface::SOURCE_IMAGE,
            ResponsiveCropInterface::CROPPED_IMAGE,
        ]) as $row) {
            $paths[] = $this->image($row[ResponsiveCropInterface::SOURCE_IMAGE]);
            $paths[] = $this->image($row[ResponsiveCropInterface::CROPPED_IMAGE]);
        }
        foreach ($this->rows(VariantRows::TABLE, [CropVariantInterface::PATH]) as $row) {
            $paths[] = $this->image($row[CropVariantInterface::PATH]);
        }
        foreach ($this->rows(SliderResource::TABLE_NAME, [SliderInterface::CUSTOM_CSS]) as $row) {
            array_push($paths, ...$this->text($row[SliderInterface::CUSTOM_CSS]));
        }

        return new MediaReferenceSnapshot(array_values(array_filter(
            $paths,
            fn (?string $path): bool => $path !== null
        )));
    }

    /**
     * Rows of a table with at least one of the columns set, each column as a string or null
     *
     * @param string $table Table name without prefix
     * @param non-empty-list<string> $columns
     * @return list<array<string, string|null>>
     */
    private function rows(string $table, array $columns): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName($table), $columns)
            ->where(implode(' OR ', array_map(fn (string $column): string => $column . ' IS NOT NULL', $columns)));

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $typed = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $typed[$column] = is_string($value) && trim($value) !== '' ? $value : null;
            }
            $rows[] = $typed;
        }

        return $rows;
    }

    /**
     * A stored image path as a media-relative path
     *
     * @param string|null $stored
     * @return string|null
     */
    private function image(?string $stored): ?string
    {
        return $stored === null ? null : $this->normaliser->normalise($stored);
    }

    /**
     * A stored local video path as a media-relative path; a remote video URL is not a media path
     *
     * @param string|null $stored
     * @return string|null
     */
    private function video(?string $stored): ?string
    {
        return $stored === null ? null : $this->normaliser->normaliseVideoPath($stored);
    }

    /**
     * Media paths quoted in stored HTML or CSS
     *
     * @param string|null $stored
     * @return list<string>
     */
    private function text(?string $stored): array
    {
        return $stored === null ? [] : $this->textExtractor->extract($stored);
    }
}
