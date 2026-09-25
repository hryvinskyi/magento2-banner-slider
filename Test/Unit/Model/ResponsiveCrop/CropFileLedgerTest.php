<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\CropVariant;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileLedger;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropWriteResult;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropFileLedger::class)]
#[CoversClass(CropWriteResult::class)]
class CropFileLedgerTest extends TestCase
{
    /**
     * A write result lists its new paths and points a crop at them
     *
     * @return void
     */
    public function testWriteResult(): void
    {
        $variant = new CropVariant('webp', 85, 'banner_slider/responsive/1/d_b.webp');
        $result = new CropWriteResult(
            'banner_slider/responsive/1/d_a.png',
            [$variant],
            ['banner_slider/responsive/1/d_b.webp'],
            ['banner_slider/responsive/1/d_old.png']
        );
        $crop = $this->createMock(ResponsiveCropInterface::class);
        $crop->expects(self::once())->method('setCroppedImage')->with('banner_slider/responsive/1/d_a.png');
        $crop->expects(self::once())->method('setVariants')->with([$variant]);

        $result->applyTo($crop);

        self::assertSame(
            ['banner_slider/responsive/1/d_a.png', 'banner_slider/responsive/1/d_b.webp'],
            $result->getNewPaths()
        );
        self::assertSame(['banner_slider/responsive/1/d_b.webp'], $result->getCreatedPaths());
        self::assertSame(['banner_slider/responsive/1/d_old.png'], $result->getObsoletePaths());
    }

    /**
     * The ledger adds up every write and release without duplicates
     *
     * @return void
     */
    public function testLedgerCollects(): void
    {
        $ledger = new CropFileLedger();
        $ledger->recordWrite(new CropWriteResult('r/1/a.png', [], ['r/1/a.png'], ['r/1/old.png']));
        $ledger->recordWrite(new CropWriteResult('r/1/b.png', [], [], ['r/1/old.png']));

        self::assertSame(['r/1/a.png'], $ledger->getCreatedPaths());
        self::assertSame(['r/1/a.png', 'r/1/b.png'], $ledger->getNewPaths());
        self::assertSame(['r/1/old.png'], $ledger->getObsoletePaths());
    }
}
