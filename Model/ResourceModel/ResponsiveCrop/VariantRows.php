<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSliderApi\Api\Data\CropVariantInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Reads and writes the variant rows of crops (`hryvinskyi_banner_slider_crop_variant`).
 *
 * Reads work in bulk, one query for any number of crops. A stored row that cannot form a variant (a malformed format
 * code or an absolute path) is left out; a quality outside 1..100 is brought back into that range. Writes replace one
 * crop's variants: rows are upserted by (crop, format) and rows of formats no longer wanted are deleted.
 */
class VariantRows
{
    public const TABLE = 'hryvinskyi_banner_slider_crop_variant';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Variants per crop, ordered by format; every requested crop is a key
     *
     * @param list<int> $cropIds
     * @return array<int, list<CropVariantInterface>>
     */
    public function fetchByCropIds(array $cropIds): array
    {
        $result = array_fill_keys($cropIds, []);
        if ($cropIds === []) {
            return $result;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName(self::TABLE),
                [
                    CropVariantInterface::CROP_ID,
                    CropVariantInterface::FORMAT,
                    CropVariantInterface::QUALITY,
                    CropVariantInterface::PATH,
                ]
            )
            ->where(CropVariantInterface::CROP_ID . ' IN (?)', $cropIds)
            ->order([CropVariantInterface::CROP_ID, CropVariantInterface::FORMAT]);

        foreach ($connection->fetchAll($select) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cropId = $row[CropVariantInterface::CROP_ID] ?? null;
            $variant = $this->toVariant($row);
            if ($variant !== null && is_numeric($cropId) && isset($result[(int)$cropId])) {
                $result[(int)$cropId][] = $variant;
            }
        }

        return $result;
    }

    /**
     * Make the crop's variant rows match the given variants
     *
     * @param int $cropId
     * @param list<CropVariantInterface> $variants
     * @return void
     */
    public function replace(int $cropId, array $variants): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $formats = [];
        $rows = [];
        foreach ($variants as $variant) {
            $formats[] = $variant->getFormat();
            $rows[] = [
                CropVariantInterface::CROP_ID => $cropId,
                CropVariantInterface::FORMAT => $variant->getFormat(),
                CropVariantInterface::QUALITY => $variant->getQuality(),
                CropVariantInterface::PATH => $variant->getPath(),
            ];
        }

        $where = [CropVariantInterface::CROP_ID . ' = ?' => $cropId];
        if ($formats !== []) {
            $where[CropVariantInterface::FORMAT . ' NOT IN (?)'] = $formats;
        }
        $connection->delete($table, $where);

        if ($rows !== []) {
            $connection->insertOnDuplicate(
                $table,
                $rows,
                [CropVariantInterface::QUALITY, CropVariantInterface::PATH]
            );
        }
    }

    /**
     * Build a variant from a stored row, or null when the row cannot form one
     *
     * @param array<mixed> $row
     * @return CropVariantInterface|null
     */
    private function toVariant(array $row): ?CropVariantInterface
    {
        $format = $row[CropVariantInterface::FORMAT] ?? null;
        $quality = $row[CropVariantInterface::QUALITY] ?? null;
        $path = $row[CropVariantInterface::PATH] ?? null;
        if (!is_string($format) || !is_numeric($quality)) {
            return null;
        }
        $quality = max(CropVariant::MIN_QUALITY, min(CropVariant::MAX_QUALITY, (int)$quality));
        $path = is_string($path) && trim($path) !== '' ? $path : null;

        try {
            return new CropVariant($format, $quality, $path);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
