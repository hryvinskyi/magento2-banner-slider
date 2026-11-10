<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\LibpngWarningTolerance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Under an error handler that throws on every warning, as Magento's does, a libpng warning raised by the operation is
 * ignored and anything else still throws
 */
#[CoversClass(LibpngWarningTolerance::class)]
class LibpngWarningToleranceTest extends TestCase
{
    private const LIBPNG_WARNING = 'imagecreatefrompng(): gd-png: libpng warning: iCCP: known incorrect sRGB profile';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        set_error_handler(static function (int $level, string $message): bool {
            throw new \ErrorException($message, 0, $level);
        });
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        restore_error_handler();
    }

    /**
     * @return void
     */
    public function testALibpngWarningDoesNotFailTheOperation(): void
    {
        $decoded = random_int(1, PHP_INT_MAX);

        $result = (new LibpngWarningTolerance())->run(static function () use ($decoded): int {
            trigger_error(self::LIBPNG_WARNING, E_USER_WARNING);

            return $decoded;
        });

        self::assertSame($decoded, $result);
    }

    /**
     * @return void
     */
    public function testAnyOtherWarningReachesTheHandlerInPlace(): void
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('gd-png error: cannot allocate image');

        (new LibpngWarningTolerance())->run(static function (): void {
            trigger_error('gd-png error: cannot allocate image', E_USER_WARNING);
        });
    }

    /**
     * @return void
     */
    public function testTheOperationsOwnFailurePassesThrough(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the operation failed');

        (new LibpngWarningTolerance())->run([$this, 'failingOperation']);
    }

    /**
     * @return void
     */
    public function testTheHandlerInPlaceIsBackAfterARun(): void
    {
        (new LibpngWarningTolerance())->run(static fn (): bool => true);

        $this->expectException(\ErrorException::class);
        trigger_error(self::LIBPNG_WARNING, E_USER_WARNING);
    }

    /**
     * @return void
     */
    public function testTheHandlerInPlaceIsBackAfterAFailedRun(): void
    {
        $tolerance = new LibpngWarningTolerance();
        $this->runAndSwallow($tolerance);

        $this->expectException(\ErrorException::class);
        trigger_error(self::LIBPNG_WARNING, E_USER_WARNING);
    }

    /**
     * An operation that fails
     *
     * @return void
     * @throws \RuntimeException Always
     */
    public function failingOperation(): void
    {
        throw new \RuntimeException('the operation failed');
    }

    /**
     * Run the failing operation and keep its exception from the test
     *
     * @param LibpngWarningTolerance $tolerance
     * @return void
     */
    private function runAndSwallow(LibpngWarningTolerance $tolerance): void
    {
        try {
            $tolerance->run([$this, 'failingOperation']);
        } catch (\RuntimeException $exception) {
            self::assertSame('the operation failed', $exception->getMessage());
        }
    }
}
