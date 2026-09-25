<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Banner;

use Hryvinskyi\BannerSlider\Model\Banner\BannerUrlPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(BannerUrlPolicy::class)]
class BannerUrlPolicyTest extends TestCase
{
    /**
     * @var BannerUrlPolicy
     */
    private BannerUrlPolicy $policy;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->policy = new BannerUrlPolicy();
    }

    /**
     * Allowed link URLs pass
     *
     * @param string $url
     * @return void
     */
    #[TestWith(['https://example.com/sale'])]
    #[TestWith(['HTTP://example.com'])]
    #[TestWith(['mailto:shop@example.com'])]
    #[TestWith(['tel:+441234567'])]
    #[TestWith(['/sale.html'])]
    #[TestWith(['#top'])]
    #[TestWith(['?page=2'])]
    #[TestWith(['sale/summer.html'])]
    public function testAllowedLinks(string $url): void
    {
        $this->policy->assertLinkUrl($url);

        $this->addToAssertionCount(1);
    }

    /**
     * Links that could run script or are malformed are rejected
     *
     * @param string $url
     * @return void
     */
    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['JavaScript:alert(1)'])]
    #[TestWith([' javascript:alert(1)'])]
    #[TestWith(["java\tscript:alert(1)"])]
    #[TestWith(['data:text/html,<script>'])]
    #[TestWith(['vbscript:msgbox'])]
    #[TestWith([''])]
    public function testRejectedLinks(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->policy->assertLinkUrl($url);
    }

    /**
     * Only absolute http(s) video URLs pass
     *
     * @param string $url
     * @param bool $allowed
     * @return void
     */
    #[TestWith(['https://www.youtube.com/watch?v=abc', true])]
    #[TestWith(['http://vimeo.com/123', true])]
    #[TestWith(['//youtube.com/watch?v=abc', false])]
    #[TestWith(['javascript:alert(1)', false])]
    #[TestWith(['mailto:a@b.c', false])]
    #[TestWith(['banner_slider/video/a.mp4', false])]
    public function testVideoUrls(string $url, bool $allowed): void
    {
        if (!$allowed) {
            $this->expectException(\InvalidArgumentException::class);
        }

        $this->policy->assertVideoUrl($url);

        $this->addToAssertionCount(1);
    }
}
