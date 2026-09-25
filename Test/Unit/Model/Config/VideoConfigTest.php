<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Config;

use Hryvinskyi\BannerSlider\Model\Config\UploadLimitReader;
use Hryvinskyi\BannerSlider\Model\Config\VideoConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(VideoConfig::class)]
class VideoConfigTest extends TestCase
{
    /**
     * Only an explicit off turns privacy-enhanced embeds off
     *
     * @param mixed $stored
     * @param bool $expected
     * @return void
     */
    #[TestWith(['1', true])]
    #[TestWith([1, true])]
    #[TestWith(['0', false])]
    #[TestWith([0, false])]
    #[TestWith([false, false])]
    #[TestWith([null, true])]
    #[TestWith(['', true])]
    #[TestWith(['maybe', true])]
    public function testPrivacyEnhanced(mixed $stored, bool $expected): void
    {
        self::assertSame(
            $expected,
            $this->config(['hryvinskyi_banner_slider/video/privacy_enhanced' => $stored])->isPrivacyEnhanced()
        );
    }

    /**
     * The privacy setting is read in the store view scope asked for
     *
     * @return void
     */
    public function testPrivacyEnhancedIsReadPerStore(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())->method('getValue')
            ->with('hryvinskyi_banner_slider/video/privacy_enhanced', ScopeInterface::SCOPE_STORE, 3)
            ->willReturn('0');

        self::assertFalse(
            (new VideoConfig($scopeConfig, new UploadLimitReader($scopeConfig)))->isPrivacyEnhanced(3)
        );
    }

    /**
     * The upload cap is read in megabytes, 100 MB when broken
     *
     * @param mixed $stored
     * @param int $expected
     * @return void
     */
    #[TestWith(['50', 52428800])]
    #[TestWith(['0', 104857600])]
    #[TestWith(['big', 104857600])]
    #[TestWith([null, 104857600])]
    public function testMaxUploadBytes(mixed $stored, int $expected): void
    {
        self::assertSame(
            $expected,
            $this->config(['hryvinskyi_banner_slider/video/max_upload_size_mb' => $stored])->getMaxUploadBytes()
        );
    }

    /**
     * Video settings over the given stored values
     *
     * @param array<string,mixed> $values
     * @return VideoConfig
     */
    private function config(array $values): VideoConfig
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): mixed => $values[$path] ?? null
        );

        return new VideoConfig($scopeConfig, new UploadLimitReader($scopeConfig));
    }
}
