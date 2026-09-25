<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Cache;

use DateTimeImmutable;
use DateTimeZone;
use Hryvinskyi\BannerSlider\Model\AbstractEntityModel;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\LocationCode;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\FlagManager;
use Psr\Clock\ClockInterface;

/**
 * Cleans the cached pages of sliders and banners whose active window opened or closed since the previous run.
 *
 * A window contains both of its ends, so a slider or banner appears at its `from_date` and disappears one second
 * after its `to_date`. A run covers the `from_date` values in (previous run, now] and the `to_date` values in
 * [previous run, now), so consecutive runs cover every moment exactly once.
 *
 * - A banner crossing a boundary cleans its own tag and its slider's.
 * - A slider crossing a boundary cleans its own tag and its location's.
 *
 * The time of the run is stored in a flag (UTC). The first run has nothing to compare with: it only stores the time.
 */
class ScheduleBoundaryRefresher
{
    public const FLAG_CODE = 'hryvinskyi_banner_slider_schedule_refresh';

    /**
     * @param ResourceConnection $resourceConnection
     * @param FlagManager $flagManager
     * @param ClockInterface $clock
     * @param TagCleaner $tagCleaner
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly FlagManager $flagManager,
        private readonly ClockInterface $clock,
        private readonly TagCleaner $tagCleaner
    ) {
    }

    /**
     * Clean the pages of everything whose window changed state since the previous run, and remember this run
     *
     * @return list<string> The tags cleaned
     */
    public function refresh(): array
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))
            ->format(AbstractEntityModel::STORED_DATETIME_FORMAT);
        $lastRun = $this->lastRun();

        $tags = [];
        if ($lastRun !== null && $lastRun < $now) {
            $tags = array_values(array_unique([
                ...$this->sliderTags($lastRun, $now),
                ...$this->bannerTags($lastRun, $now),
            ]));
            $this->tagCleaner->clean($tags);
        }
        $this->flagManager->saveFlag(self::FLAG_CODE, $now);

        return $tags;
    }

    /**
     * The stored time of the previous run, or null when there is none
     *
     * @return string|null UTC time in the stored date-time format
     */
    private function lastRun(): ?string
    {
        $value = $this->flagManager->getFlagData(self::FLAG_CODE);
        if (!is_string($value)) {
            return null;
        }
        $time = DateTimeImmutable::createFromFormat(
            AbstractEntityModel::STORED_DATETIME_FORMAT,
            $value,
            new DateTimeZone('UTC')
        );

        return $time === false ? null : $time->format(AbstractEntityModel::STORED_DATETIME_FORMAT);
    }

    /**
     * Tags of sliders that crossed a boundary: the slider's own and its location's
     *
     * @param string $from
     * @param string $to
     * @return list<string>
     */
    private function sliderTags(string $from, string $to): array
    {
        $tags = [];
        foreach ($this->crossedRows(SliderResource::TABLE_NAME, [
            SliderInterface::SLIDER_ID,
            SliderInterface::LOCATION,
        ], $from, $to) as $row) {
            $sliderId = $row[SliderInterface::SLIDER_ID];
            if (is_numeric($sliderId)) {
                $tags[] = SliderInterface::CACHE_TAG . '_' . (int)$sliderId;
            }
            $location = $row[SliderInterface::LOCATION];
            $locationTag = is_string($location) ? $this->locationTag($location) : null;
            if ($locationTag !== null) {
                $tags[] = $locationTag;
            }
        }

        return $tags;
    }

    /**
     * Tags of banners that crossed a boundary: the banner's own and its slider's
     *
     * @param string $from
     * @param string $to
     * @return list<string>
     */
    private function bannerTags(string $from, string $to): array
    {
        $tags = [];
        foreach ($this->crossedRows(BannerResource::TABLE_NAME, [
            BannerInterface::BANNER_ID,
            BannerInterface::SLIDER_ID,
        ], $from, $to) as $row) {
            $bannerId = $row[BannerInterface::BANNER_ID];
            if (is_numeric($bannerId)) {
                $tags[] = BannerInterface::CACHE_TAG . '_' . (int)$bannerId;
            }
            $sliderId = $row[BannerInterface::SLIDER_ID];
            if (is_numeric($sliderId)) {
                $tags[] = SliderInterface::CACHE_TAG . '_' . (int)$sliderId;
            }
        }

        return $tags;
    }

    /**
     * Rows whose window opened in (from, to] or closed in [from, to)
     *
     * @param string $table Table name without prefix
     * @param list<string> $columns
     * @param string $from
     * @param string $to
     * @return list<array<string, mixed>>
     */
    private function crossedRows(string $table, array $columns, string $from, string $to): array
    {
        $connection = $this->resourceConnection->getConnection();
        $opened = $connection->quoteInto(SliderInterface::FROM_DATE . ' > ?', $from)
            . ' AND ' . $connection->quoteInto(SliderInterface::FROM_DATE . ' <= ?', $to);
        $closed = $connection->quoteInto(SliderInterface::TO_DATE . ' >= ?', $from)
            . ' AND ' . $connection->quoteInto(SliderInterface::TO_DATE . ' < ?', $to);
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName($table), $columns)
            ->where('(' . $opened . ') OR (' . $closed . ')');

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $typed = [];
            foreach ($columns as $column) {
                $typed[$column] = $row[$column] ?? null;
            }
            $rows[] = $typed;
        }

        return $rows;
    }

    /**
     * The cache tag of a stored location, or null when it is empty or not a valid location code
     *
     * @param string $location
     * @return string|null
     */
    private function locationTag(string $location): ?string
    {
        try {
            return $location === '' ? null : (new LocationCode($location))->toCacheTag();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
