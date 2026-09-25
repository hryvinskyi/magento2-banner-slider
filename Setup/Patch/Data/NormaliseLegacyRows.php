<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Migration\LegacyMediaPathNormaliser;
use Hryvinskyi\BannerSlider\Model\Migration\SiteBaseUrls;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Makes legacy rows readable through the typed models and the media services.
 *
 * - Stored media values become media-relative paths (see LegacyMediaPathNormaliser); a value that cannot become one
 *   is kept as stored and logged. An absolute URL is rewritten only when it points into the media folder on one of
 *   this site's hosts (the store views' base URLs); a URL on another host is kept.
 * - A slider or banner whose active window ends before it starts was never shown; it is disabled and its end is set
 *   to its start, so the window is valid again. Each id is logged.
 * - Banners with an unknown type are counted in the log; the model maps them when read.
 */
class NormaliseLegacyRows implements DataPatchInterface
{
    private const SLIDER_TABLE = 'hryvinskyi_banner_slider';
    private const BANNER_TABLE = 'hryvinskyi_banner_slider_banner';
    private const CROP_TABLE = 'hryvinskyi_banner_slider_responsive_crop';
    private const VARIANT_TABLE = 'hryvinskyi_banner_slider_crop_variant';

    /**
     * Columns holding image paths: [table, id column, path column]
     */
    private const IMAGE_COLUMNS = [
        [self::BANNER_TABLE, 'banner_id', 'image'],
        [self::CROP_TABLE, 'crop_id', 'source_image'],
        [self::CROP_TABLE, 'crop_id', 'cropped_image'],
        [self::VARIANT_TABLE, 'variant_id', 'path'],
    ];

    /**
     * Columns holding local video paths: [table, id column, path column]
     */
    private const VIDEO_COLUMNS = [
        [self::BANNER_TABLE, 'banner_id', 'video_path'],
    ];

    /**
     * Tables with an active window: table => id column
     */
    private const WINDOW_TABLES = [
        self::SLIDER_TABLE => 'slider_id',
        self::BANNER_TABLE => 'banner_id',
    ];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LegacyMediaPathNormaliser $pathNormaliser
     * @param SiteBaseUrls $siteBaseUrls
     * @param TypedRowFetcher $rowFetcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LegacyMediaPathNormaliser $pathNormaliser,
        private readonly SiteBaseUrls $siteBaseUrls,
        private readonly TypedRowFetcher $rowFetcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $baseUrls = $this->siteBaseUrls->getAll();
        $normaliseImage = fn (string $value): ?string => $this->pathNormaliser->normalise($value, $baseUrls);
        $normaliseVideo = fn (string $value): ?string => $this->pathNormaliser->normaliseVideoPath($value, $baseUrls);

        foreach (self::IMAGE_COLUMNS as [$table, $idColumn, $column]) {
            $this->normaliseColumn($connection, $table, $idColumn, $column, $normaliseImage);
        }
        foreach (self::VIDEO_COLUMNS as [$table, $idColumn, $column]) {
            $this->normaliseColumn($connection, $table, $idColumn, $column, $normaliseVideo);
        }
        foreach (self::WINDOW_TABLES as $table => $idColumn) {
            $this->closeInvertedWindows($connection, $table, $idColumn);
        }
        $this->reportUnknownBannerTypes($connection);

        return $this;
    }

    /**
     * Rewrite the stored paths of one column through the normaliser
     *
     * @param AdapterInterface $connection
     * @param string $table Table name without prefix
     * @param string $idColumn
     * @param string $column
     * @param \Closure(string):(string|null) $normalise
     * @return void
     */
    private function normaliseColumn(
        AdapterInterface $connection,
        string $table,
        string $idColumn,
        string $column,
        \Closure $normalise
    ): void {
        $tableName = $this->moduleDataSetup->getTable($table);
        $stored = $this->rowFetcher->fetchIdValuePairs(
            $connection,
            $connection->select()
                ->from($tableName, [$idColumn, $column])
                ->where($column . ' IS NOT NULL')
                ->where($column . " <> ''")
        );

        $rewritten = 0;
        $unsafe = [];
        foreach ($stored as $id => $value) {
            $value = (string)$value;
            $path = $normalise($value);
            if ($path === null) {
                $unsafe[$id] = $value;
                continue;
            }
            if ($path === $value) {
                continue;
            }
            $connection->update($tableName, [$column => $path], [$idColumn . ' = ?' => $id]);
            $rewritten++;
        }

        if ($rewritten > 0) {
            $this->logger->info(sprintf(
                'Banner slider migration: %d values of %s.%s rewritten as media-relative paths.',
                $rewritten,
                $table,
                $column
            ));
        }
        if ($unsafe !== []) {
            $this->logger->warning(
                sprintf(
                    'Banner slider migration: %s.%s values kept as stored; they are not safe media-relative paths.',
                    $table,
                    $column
                ),
                ['values_by_id' => $unsafe]
            );
        }
    }

    /**
     * Disable rows whose window ends before it starts and set the end to the start
     *
     * @param AdapterInterface $connection
     * @param string $table Table name without prefix
     * @param string $idColumn
     * @return void
     */
    private function closeInvertedWindows(AdapterInterface $connection, string $table, string $idColumn): void
    {
        $tableName = $this->moduleDataSetup->getTable($table);
        $ids = $this->rowFetcher->fetchIds(
            $connection,
            $connection->select()
                ->from($tableName, [$idColumn])
                ->where('from_date IS NOT NULL')
                ->where('to_date IS NOT NULL')
                ->where('from_date > to_date')
        );
        if ($ids === []) {
            return;
        }

        $connection->update(
            $tableName,
            ['status' => 0, 'to_date' => new Expression('from_date')],
            [$idColumn . ' IN (?)' => $ids]
        );
        $this->logger->warning(
            sprintf(
                'Banner slider migration: %s rows ended before they started; disabled, end set to start.',
                $table
            ),
            ['ids' => $ids]
        );
    }

    /**
     * Log how many banners carry a type that is not a known banner type
     *
     * @param AdapterInterface $connection
     * @return void
     */
    private function reportUnknownBannerTypes(AdapterInterface $connection): void
    {
        $knownTypes = array_map(fn (BannerType $type): int => $type->value, BannerType::cases());
        $count = $this->rowFetcher->fetchInt(
            $connection,
            $connection->select()
                ->from($this->moduleDataSetup->getTable(self::BANNER_TABLE), [new Expression('COUNT(*)')])
                ->where('type NOT IN (?)', $knownTypes)
        );
        if ($count > 0) {
            $this->logger->warning(sprintf(
                'Banner slider migration: %d banners have an unknown type; they are read as custom banners.',
                $count
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [MigrateCropVariants::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
