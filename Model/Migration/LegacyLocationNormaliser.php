<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Migration;

use Hryvinskyi\BannerSliderApi\Api\Value\LocationCode;

/**
 * Turns a stored slider location into a valid location code (see LocationCode), or null when nothing is left.
 *
 * Earlier versions accepted any text as a location. A valid code is kept as it is. Otherwise surrounding whitespace
 * is trimmed, every character that is not a letter, a digit, `_` or `-` becomes `_` (one per character, also for a
 * multibyte character), and the result is cut to 255 characters. An empty result is no location.
 */
class LegacyLocationNormaliser
{
    private const INVALID_CHARACTER = '/[^A-Za-z0-9_-]/';
    private const MAX_LENGTH = 255;

    /**
     * The valid location code for a stored location
     *
     * @param string $stored
     * @return string|null Null when the location leaves nothing to place a slider by
     */
    public function normalise(string $stored): ?string
    {
        if ($this->isValid($stored)) {
            return $stored;
        }

        $trimmed = trim($stored);
        $replaced = preg_replace(self::INVALID_CHARACTER . 'u', '_', $trimmed)
            ?? preg_replace(self::INVALID_CHARACTER, '_', $trimmed)
            ?? '';
        $code = substr($replaced, 0, self::MAX_LENGTH);

        return $this->isValid($code) ? $code : null;
    }

    /**
     * Whether the value is a valid location code
     *
     * @param string $value
     * @return bool
     */
    private function isValid(string $value): bool
    {
        try {
            new LocationCode($value);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
