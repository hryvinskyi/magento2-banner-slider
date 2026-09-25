<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\OriginalFormatRule;
use Hryvinskyi\BannerSliderApi\Api\Image\ImageFormatRegistryInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(OriginalFormatRule::class)]
class OriginalFormatRuleTest extends TestCase
{
    /**
     * A JPEG source keeps JPEG; every other source becomes PNG
     *
     * @param string $source
     * @param string $expected
     * @return void
     */
    #[TestWith(['jpeg', 'jpeg'])]
    #[TestWith(['png', 'png'])]
    #[TestWith(['gif', 'png'])]
    #[TestWith(['webp', 'png'])]
    #[TestWith(['avif', 'png'])]
    public function testOriginalFormat(string $source, string $expected): void
    {
        $registry = $this->createMock(ImageFormatRegistryInterface::class);
        $registry->method('get')->willReturnCallback(
            static fn (string $code): ImageFormat => new ImageFormat($code, 'image/' . $code, $code)
        );

        self::assertSame(
            $expected,
            (new OriginalFormatRule($registry))->resolve(new ImageFormat($source, 'image/' . $source, $source))
                ->getCode()
        );
    }
}
