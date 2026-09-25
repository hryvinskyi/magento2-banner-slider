<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use DateTimeImmutable;
use DateTimeZone;
use Hryvinskyi\BannerSliderApi\Api\Value\ActiveWindow;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Base of the slider, banner, breakpoint and crop models: reads stored column values as typed PHP values.
 *
 * Stored values arrive as strings from the database and as native values from setters; the readers accept both and
 * return null (or the given default) for anything they cannot read, so a getter never fails on a legacy row. Dates
 * are stored in UTC as `Y-m-d H:i:s`.
 */
abstract class AbstractEntityModel extends AbstractExtensibleModel
{
    /**
     * Format of the stored UTC `datetime` columns
     */
    public const STORED_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Integer value of a stored field, or null when it is absent or not a whole number
     *
     * @param string $key
     * @return int|null
     */
    protected function readInt(string $key): ?int
    {
        $value = $this->getData($key);
        if (is_int($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_string($value) && preg_match('/^\s*-?\d+\s*$/', $value) === 1) {
            return (int)$value;
        }

        return is_float($value) ? (int)$value : null;
    }

    /**
     * Id stored in a field: a whole number greater than 0, otherwise null
     *
     * @param string $key
     * @return int|null
     */
    protected function readId(string $key): ?int
    {
        $value = $this->readInt($key);

        return $value !== null && $value > 0 ? $value : null;
    }

    /**
     * String value of a stored field, or null when it is absent or not a scalar
     *
     * @param string $key
     * @return string|null
     */
    protected function readString(string $key): ?string
    {
        $value = $this->getData($key);
        if (is_string($value)) {
            return $value;
        }

        return is_int($value) || is_float($value) ? (string)$value : null;
    }

    /**
     * String value of a stored field, or null when it is absent or blank
     *
     * @param string $key
     * @return string|null
     */
    protected function readNonBlankString(string $key): ?string
    {
        $value = $this->readString($key);

        return $value === null || trim($value) === '' ? null : $value;
    }

    /**
     * Flag value of a stored field; the default when it is absent or unreadable
     *
     * @param string $key
     * @param bool $default
     * @return bool
     */
    protected function readBool(string $key, bool $default): bool
    {
        $value = $this->getData($key);
        if (is_bool($value)) {
            return $value;
        }
        $number = $this->readInt($key);

        return $number === null ? $default : $number !== 0;
    }

    /**
     * Non-negative whole numbers of a stored list field (a data key filled by a resource model)
     *
     * @param string $key
     * @return list<int>
     */
    protected function readIdList(string $key): array
    {
        $value = $this->getData($key);
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_int($item) && $item >= 0) {
                $ids[] = $item;
                continue;
            }
            if (is_string($item) && ctype_digit($item)) {
                $ids[] = (int)$item;
            }
        }

        return $ids;
    }

    /**
     * Active window from two stored UTC date fields
     *
     * An unreadable end reads as open. A stored window whose end lies before its start reads as the single moment of
     * its start, so the row can still be opened and corrected.
     *
     * @param string $fromKey
     * @param string $toKey
     * @return ActiveWindow
     */
    protected function readActiveWindow(string $fromKey, string $toKey): ActiveWindow
    {
        $from = $this->readDateTime($fromKey);
        $to = $this->readDateTime($toKey);
        if ($from !== null && $to !== null && $from > $to) {
            return new ActiveWindow($from, $from);
        }

        return new ActiveWindow($from, $to);
    }

    /**
     * Store an active window into two UTC date fields
     *
     * @param ActiveWindow $window
     * @param string $fromKey
     * @param string $toKey
     * @return void
     */
    protected function storeActiveWindow(ActiveWindow $window, string $fromKey, string $toKey): void
    {
        $this->setData($fromKey, $window->getFrom()?->format(self::STORED_DATETIME_FORMAT));
        $this->setData($toKey, $window->getTo()?->format(self::STORED_DATETIME_FORMAT));
    }

    /**
     * Reject an id that is not greater than 0
     *
     * @param string $label
     * @param int $id
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function assertPositiveId(string $label, int $id): void
    {
        if ($id < 1) {
            throw new \InvalidArgumentException(sprintf('%s must be greater than 0, got %d.', $label, $id));
        }
    }

    /**
     * Reject a negative number
     *
     * @param string $label
     * @param int $value
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function assertNotNegative(string $label, int $value): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException(sprintf('%s must be zero or greater, got %d.', $label, $value));
        }
    }

    /**
     * Reject a blank name
     *
     * @param string $label
     * @param string $value
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function assertNotBlank(string $label, string $value): void
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException(sprintf('%s must not be empty.', $label));
        }
    }

    /**
     * UTC moment stored in a date field, or null when it is absent or unreadable
     *
     * @param string $key
     * @return DateTimeImmutable|null
     */
    private function readDateTime(string $key): ?DateTimeImmutable
    {
        $value = $this->readNonBlankString($key);
        if ($value === null) {
            return null;
        }

        $utc = new DateTimeZone('UTC');
        $parsed = DateTimeImmutable::createFromFormat('!' . self::STORED_DATETIME_FORMAT, $value, $utc);
        if ($parsed !== false) {
            return $parsed;
        }

        try {
            return new DateTimeImmutable($value, $utc);
        } catch (\Exception) {
            return null;
        }
    }
}
