<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Image;

use Hryvinskyi\BannerSlider\Model\Image\EncodingException;
use Hryvinskyi\BannerSlider\Model\Image\GdImageDecoder;
use Hryvinskyi\BannerSlider\Model\Image\RuntimeCapabilities;
use Magento\Framework\Filesystem\Driver\File as LocalFileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(GdImageDecoder::class)]
class GdImageDecoderTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../_files/images/';

    /**
     * A format is decodable only when it is mapped to a GD feature the runtime reports, and GD can decode at all
     *
     * @return void
     */
    public function testCanDecodeFollowsTheRuntime(): void
    {
        $runtime = $this->createMock(RuntimeCapabilities::class);
        $runtime->method('hasFunction')->willReturnCallback(
            static fn (string $function): bool => $function === 'imagecreatefromstring'
        );
        $runtime->method('gdSupports')->willReturnCallback(
            static fn (string $feature): bool => $feature === 'PNG Support'
        );
        $decoder = new GdImageDecoder($runtime, ['png' => 'PNG Support', 'avif' => 'AVIF Support']);

        self::assertTrue($decoder->canDecode('png'));
        self::assertFalse($decoder->canDecode('avif'));
        self::assertFalse($decoder->canDecode('jpeg'));

        $withoutGd = $this->createMock(RuntimeCapabilities::class);
        $withoutGd->method('hasFunction')->willReturn(false);
        $withoutGd->method('gdSupports')->willReturn(true);
        self::assertFalse((new GdImageDecoder($withoutGd, ['png' => 'PNG Support']))->canDecode('png'));
    }

    /**
     * A complete image decodes
     *
     * @return void
     */
    public function testDecodesAnImage(): void
    {
        $decoder = $this->realDecoder();

        $decoder->decode((new LocalFileDriver())->fileGetContents(self::FIXTURES . '2x2.png'));
        $this->addToAssertionCount(1);
    }

    /**
     * Bytes whose header reads but whose body is cut off, bytes of no image, and no bytes at all do not decode
     *
     * @param string $case
     * @return void
     */
    #[TestWith(['truncated'])]
    #[TestWith(['text'])]
    #[TestWith(['empty'])]
    public function testCorruptBytesFail(string $case): void
    {
        $decoder = $this->realDecoder();
        $png = (new LocalFileDriver())->fileGetContents(self::FIXTURES . '2x2.png');
        $bytes = match ($case) {
            'truncated' => substr($png, 0, 40),
            'text' => 'not an image',
            default => '',
        };

        $this->expectException(EncodingException::class);
        $decoder->decode($bytes);
    }

    /**
     * A decoder over the real runtime, or a skipped test without GD
     *
     * @return GdImageDecoder
     */
    private function realDecoder(): GdImageDecoder
    {
        $decoder = new GdImageDecoder(new RuntimeCapabilities(), ['png' => 'PNG Support']);
        if (!$decoder->canDecode('png')) {
            self::markTestSkipped('GD cannot decode PNG on this runtime.');
        }

        return $decoder;
    }
}
