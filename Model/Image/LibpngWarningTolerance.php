<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

/**
 * Runs an image operation so that libpng's warnings do not fail it.
 *
 * libpng reports harmless defects of a PNG, such as "iCCP: known incorrect sRGB profile", as warnings and decodes the
 * image all the same. A GD build that passes them on raises a PHP warning ("gd-png: libpng warning: …"), which an
 * error handler that turns every warning into an exception (Magento's does) makes fatal. Inside run() such a warning
 * is ignored; every other error goes to the handler that was in place, so a real failure still fails.
 */
class LibpngWarningTolerance
{
    private const LIBPNG_WARNING = 'libpng warning:';
    private const LEVELS = E_WARNING | E_USER_WARNING;

    /**
     * Run the operation, ignoring the libpng warnings it raises
     *
     * @template T
     * @param callable():T $operation
     * @return T What the operation returns
     */
    public function run(callable $operation): mixed
    {
        $previous = null;
        $previous = set_error_handler(
            static function (int $level, string $message, string $file = '', int $line = 0) use (&$previous): bool {
                if (str_contains($message, self::LIBPNG_WARNING)) {
                    return true;
                }

                return is_callable($previous) && (bool)$previous($level, $message, $file, $line);
            },
            self::LEVELS
        );
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
