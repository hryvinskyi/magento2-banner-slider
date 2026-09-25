<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Media;

use Hryvinskyi\BannerSlider\Model\Media\TextMediaReferenceExtractor;
use Hryvinskyi\BannerSlider\Model\Migration\LegacyMediaPathNormaliser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextMediaReferenceExtractor::class)]
class TextMediaReferenceExtractorTest extends TestCase
{
    /**
     * Media directives, media URLs in attributes and CSS `url()` values become media-relative paths
     *
     * @param string $text
     * @param list<string> $expected
     * @return void
     */
    #[TestWith(['<img src="{{media url="wysiwyg/a.jpg"}}"/>', ['wysiwyg/a.jpg']])]
    #[TestWith(['<img src="{{media url=&quot;wysiwyg/b.jpg&quot;}}"/>', ['wysiwyg/b.jpg']])]
    #[TestWith(["{{media url='wysiwyg/c.png'}} {{media url=wysiwyg/d.png}}", ['wysiwyg/c.png', 'wysiwyg/d.png']])]
    #[TestWith(['<img src="/media/banner_slider/image/e.jpg?v=2">', ['banner_slider/image/e.jpg']])]
    #[TestWith(['<img src="https://shop.test/pub/media/f%20g.jpg#x">', ['f g.jpg']])]
    #[TestWith(['<a href=//cdn.test/media/doc.pdf>x</a>', ['doc.pdf']])]
    #[TestWith(['<img srcset="/media/s1.jpg 1x, /media/s2.jpg 2x">', ['s1.jpg', 's2.jpg']])]
    #[TestWith(['<video poster="/media/p.jpg" data-src="/media/v.mp4">', ['p.jpg', 'v.mp4']])]
    #[TestWith(['.hero { background: url("/media/bg.png") } .x { background: url(/pub/media/y.png) }',
        ['bg.png', 'y.png']])]
    #[TestWith([".z { background-image: url('https://shop.test/media/z.webp'); }", ['z.webp']])]
    public function testExtractsReferences(string $text, array $expected): void
    {
        self::assertSame($expected, $this->extractor()->extract($text));
    }

    /**
     * Values that do not point into media, or cannot be a safe media path, are left out
     *
     * @param string $text
     * @return void
     */
    #[TestWith(['<img src="/static/frontend/logo.png"><a href="https://example.test/page">x</a>'])]
    #[TestWith(['<img src="relative/picture.jpg">'])]
    #[TestWith(['<img src="/media/../app/etc/env.php">'])]
    #[TestWith(['.a { background: url(data:image/png;base64,AAAA) }'])]
    #[TestWith(['   '])]
    #[TestWith(['plain text without any reference'])]
    public function testIgnoresOtherValues(string $text): void
    {
        self::assertSame([], $this->extractor()->extract($text));
    }

    /**
     * A path quoted twice is listed once
     *
     * @return void
     */
    public function testListsEachPathOnce(): void
    {
        self::assertSame(
            ['wysiwyg/a.jpg'],
            $this->extractor()->extract('{{media url="wysiwyg/a.jpg"}}<img src="/media/wysiwyg/a.jpg">')
        );
    }

    /**
     * The extractor under test
     *
     * @return TextMediaReferenceExtractor
     */
    private function extractor(): TextMediaReferenceExtractor
    {
        return new TextMediaReferenceExtractor(new LegacyMediaPathNormaliser());
    }
}
