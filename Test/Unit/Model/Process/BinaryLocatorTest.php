<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Process;

use Hryvinskyi\BannerSlider\Model\Process\BinaryLocator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryLocator::class)]
class BinaryLocatorTest extends TestCase
{
    private const UNIT_ROOT = __DIR__ . '/../..';

    /**
     * The first executable file with the name is found, relative folders resolved against the project root
     *
     * @return void
     */
    public function testFindsExecutableInConfiguredFolders(): void
    {
        $locator = $this->locator(['vendor/bin', '_files/bin']);

        self::assertSame(self::UNIT_ROOT . '/_files/bin/fake-encoder', $locator->locate('fake-encoder'));
    }

    /**
     * Absolute folders are searched as they are
     *
     * @return void
     */
    public function testAbsoluteFolder(): void
    {
        $locator = $this->locator([self::UNIT_ROOT . '/_files/bin/']);

        self::assertSame(self::UNIT_ROOT . '/_files/bin/fake-encoder', $locator->locate('fake-encoder'));
    }

    /**
     * Missing, non-executable, folder and path-like names are not found
     *
     * @param string $binary
     * @return void
     */
    #[TestWith(['missing-encoder'])]
    #[TestWith(['not-executable'])]
    #[TestWith(['folder-encoder'])]
    #[TestWith(['../bin/fake-encoder'])]
    #[TestWith(['bin/fake-encoder'])]
    #[TestWith([''])]
    #[TestWith(['-fake-encoder'])]
    public function testNotFound(string $binary): void
    {
        self::assertNull($this->locator(['_files/bin'])->locate($binary));
    }

    /**
     * A locator whose project root is the unit test folder
     *
     * @param list<string> $directories
     * @return BinaryLocator
     */
    private function locator(array $directories): BinaryLocator
    {
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn(self::UNIT_ROOT . '/');

        return new BinaryLocator($directoryList, new LocalFileDriver(), $directories);
    }
}
