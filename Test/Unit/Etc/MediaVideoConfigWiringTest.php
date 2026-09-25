<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Etc;

use Hryvinskyi\BannerSlider\Model\Config\ImageConfig;
use Hryvinskyi\BannerSlider\Model\Config\VideoConfig;
use Hryvinskyi\BannerSlider\Model\Media\ImageUpload;
use Hryvinskyi\BannerSlider\Model\Media\MediaUrlResolver;
use Hryvinskyi\BannerSlider\Model\Media\VideoUpload;
use Hryvinskyi\BannerSlider\Model\Video\ProviderResolver;
use Hryvinskyi\BannerSliderApi\Api\Config\ImageConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Config\VideoConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\ImageUploadInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\VideoUploadInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderResolverInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * The upload, media URL, video and settings services are bound in `di.xml`, and `config.xml` ships their defaults.
 */
#[CoversNothing]
class MediaVideoConfigWiringTest extends TestCase
{
    private const DI_FILE = __DIR__ . '/../../../etc/di.xml';
    private const CONFIG_FILE = __DIR__ . '/../../../etc/config.xml';

    /**
     * Each published service has a preference to an implementation of it
     *
     * @param class-string $interface
     * @param class-string $implementation
     * @return void
     */
    #[TestWith([ImageUploadInterface::class, ImageUpload::class])]
    #[TestWith([VideoUploadInterface::class, VideoUpload::class])]
    #[TestWith([MediaUrlResolverInterface::class, MediaUrlResolver::class])]
    #[TestWith([ImageConfigInterface::class, ImageConfig::class])]
    #[TestWith([VideoConfigInterface::class, VideoConfig::class])]
    #[TestWith([ProviderResolverInterface::class, ProviderResolver::class])]
    public function testPreference(string $interface, string $implementation): void
    {
        $preferences = $this->xpath(self::DI_FILE)->query(sprintf('/config/preference[@for="%s"]/@type', $interface));

        self::assertNotFalse($preferences);
        self::assertSame(1, $preferences->length, $interface);
        self::assertSame($implementation, $preferences->item(0)?->nodeValue);
        self::assertTrue(is_subclass_of($implementation, $interface));
    }

    /**
     * The provider pool holds both iframe providers and one local provider per accepted video type
     *
     * @return void
     */
    public function testVideoProviderPoolMatchesAcceptedVideoTypes(): void
    {
        $xpath = $this->xpath(self::DI_FILE);
        $pool = $this->values($xpath, sprintf(
            '/config/type[@name="%s"]/arguments/argument[@name="providers"]/item/@name',
            ProviderResolver::class
        ));
        $storedExtensions = $this->values($xpath, sprintf(
            '/config/type[@name="%s"]/arguments/argument[@name="allowedTypes"]/item/item[@name="extension"]',
            VideoUpload::class
        ));
        $mimeTypes = $this->values($xpath, sprintf(
            '/config/type[@name="%s"]/arguments/argument[@name="allowedTypes"]/item/item[@name="mime"]',
            VideoUpload::class
        ));

        self::assertSame(['youtube', 'vimeo', 'local_mp4', 'local_webm'], $pool);
        self::assertSame(['mp4', 'webm'], array_values(array_unique($storedExtensions)));
        self::assertSame(['video/mp4', 'video/x-m4v', 'video/webm'], $mimeTypes);
    }

    /**
     * The shipped defaults of the admin settings
     *
     * @param string $path
     * @param string $value
     * @return void
     */
    #[TestWith(['image/default_formats', 'webp'])]
    #[TestWith(['image/webp_quality', '85'])]
    #[TestWith(['image/avif_quality', '80'])]
    #[TestWith(['image/jpeg_quality', '90'])]
    #[TestWith(['image/max_upload_size_mb', '10'])]
    #[TestWith(['video/privacy_enhanced', '1'])]
    #[TestWith(['video/max_upload_size_mb', '100'])]
    #[TestWith(['media/orphan_sweep_enabled', '0'])]
    public function testConfigDefault(string $path, string $value): void
    {
        self::assertSame(
            [$value],
            $this->values($this->xpath(self::CONFIG_FILE), '/config/default/hryvinskyi_banner_slider/' . $path)
        );
    }

    /**
     * The package media folders are storage-sync resources only; content editors are not offered them in the media
     * gallery, because the package owns and may delete the files there
     *
     * @return void
     */
    public function testPackageFoldersAreNotMediaGalleryFolders(): void
    {
        $xpath = $this->xpath(self::CONFIG_FILE);
        $resources = '/config/default/system/media_storage_configuration/allowed_resources';

        self::assertSame([], $this->values($xpath, $resources . '/media_gallery_image_folders/*'));
        self::assertSame(['banner_slider/image'], $this->values($xpath, $resources . '/banner_slider_image_folder'));
    }

    /**
     * An XPath over an XML file of the module
     *
     * @param string $file
     * @return \DOMXPath
     */
    private function xpath(string $file): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($file));

        return new \DOMXPath($document);
    }

    /**
     * The text values of the nodes an XPath query selects
     *
     * @param \DOMXPath $xpath
     * @param string $query
     * @return list<string>
     */
    private function values(\DOMXPath $xpath, string $query): array
    {
        $nodes = $xpath->query($query);
        self::assertNotFalse($nodes);
        $values = [];
        foreach ($nodes as $node) {
            $values[] = (string)$node->nodeValue;
        }

        return $values;
    }
}
