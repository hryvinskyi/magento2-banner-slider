<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Media\MediaPaths;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileNamer;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropFileNamer::class)]
class CropFileNamerTest extends TestCase
{
    /**
     * @var CropFileNamer
     */
    private CropFileNamer $namer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->namer = new CropFileNamer(new MediaPaths(['responsive' => 'banner_slider/responsive']));
    }

    /**
     * The name holds the banner id folder, the identifier, twelve hash characters and the canonical extension
     *
     * @return void
     */
    public function testNameFromBytes(): void
    {
        $hash = $this->namer->contentHash('encoded bytes');

        self::assertSame(hash('sha256', 'encoded bytes'), $hash);
        self::assertSame(
            'banner_slider/responsive/42/desktop_' . substr($hash, 0, 12) . '.webp',
            $this->namer->name(42, 'desktop', $hash, new ImageFormat('webp', 'image/webp', 'webp'))
        );
    }

    /**
     * Different bytes give a different name; the same bytes the same name
     *
     * @return void
     */
    public function testNameFollowsContent(): void
    {
        $format = new ImageFormat('jpeg', 'image/jpeg', 'jpg');
        $first = $this->namer->name(1, 'mobile', $this->namer->contentHash('a'), $format);

        self::assertSame($first, $this->namer->name(1, 'mobile', $this->namer->contentHash('a'), $format));
        self::assertNotSame($first, $this->namer->name(1, 'mobile', $this->namer->contentHash('b'), $format));
    }

    /**
     * An identifier that breaks the breakpoint identifier rule never reaches a path
     *
     * @param string $identifier
     * @return void
     */
    #[TestWith(['../etc'])]
    #[TestWith(['Desktop'])]
    #[TestWith(['a/b'])]
    #[TestWith([''])]
    #[TestWith(['-desktop'])]
    public function testIdentifierRejected(string $identifier): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->namer->name(1, $identifier, str_repeat('a', 64), new ImageFormat('png', 'image/png', 'png'));
    }

    /**
     * A banner id below 1 or a hash that is not lowercase hexadecimal is refused
     *
     * @param int $bannerId
     * @param string $hash
     * @return void
     */
    #[TestWith([0, 'aaaaaaaaaaaa'])]
    #[TestWith([1, 'AAAAAAAAAAAA'])]
    #[TestWith([1, 'abc'])]
    #[TestWith([1, '../../aaaaaa'])]
    public function testBannerIdAndHashRejected(int $bannerId, string $hash): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->namer->name($bannerId, 'desktop', $hash, new ImageFormat('png', 'image/png', 'png'));
    }
}
