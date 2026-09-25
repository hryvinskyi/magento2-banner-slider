<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Etc;

use Hryvinskyi\BannerSlider\Console\Command\CleanupMedia;
use Hryvinskyi\BannerSlider\Console\Command\RegenerateCrops;
use Hryvinskyi\BannerSlider\Cron\SweepOrphanMedia;
use Hryvinskyi\BannerSlider\Model\Banner\BannerEditor;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropRegenerator;
use Hryvinskyi\BannerSliderApi\Api\Banner\BannerEditorInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCrop\CropRegeneratorInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * The banner editor, the crop regenerator, both console commands and the sweep job are registered.
 */
#[CoversNothing]
class CropPipelineWiringTest extends TestCase
{
    private const DI_FILE = __DIR__ . '/../../../etc/di.xml';
    private const CRONTAB_FILE = __DIR__ . '/../../../etc/crontab.xml';

    /**
     * Each published service has a preference to an implementation of it
     *
     * @param class-string $interface
     * @param class-string $implementation
     * @return void
     */
    #[TestWith([BannerEditorInterface::class, BannerEditor::class])]
    #[TestWith([CropRegeneratorInterface::class, CropRegenerator::class])]
    public function testPreference(string $interface, string $implementation): void
    {
        self::assertSame(
            [$implementation],
            $this->values(self::DI_FILE, sprintf('/config/preference[@for="%s"]/@type', $interface))
        );
        self::assertTrue(is_subclass_of($implementation, $interface));
    }

    /**
     * Both commands are in the console command list, and every service they take is a proxy
     *
     * @return void
     */
    public function testCommandsAreRegisteredWithProxies(): void
    {
        self::assertSame(
            [RegenerateCrops::class, CleanupMedia::class],
            $this->values(
                self::DI_FILE,
                '/config/type[@name="Magento\Framework\Console\CommandListInterface"]'
                . '/arguments/argument[@name="commands"]/item'
            )
        );
        foreach ([RegenerateCrops::class => 3, CleanupMedia::class => 1] as $command => $count) {
            $arguments = $this->values(self::DI_FILE, sprintf('/config/type[@name="%s"]/arguments/argument', $command));
            self::assertCount($count, $arguments, $command);
            foreach ($arguments as $argument) {
                self::assertStringEndsWith('\Proxy', $argument, $command);
            }
        }
    }

    /**
     * The sweep job runs daily at 03:30
     *
     * @return void
     */
    public function testSweepJobIsScheduled(): void
    {
        self::assertSame(
            ['30 3 * * *'],
            $this->values(self::CRONTAB_FILE, sprintf('//job[@instance="%s"]/schedule', SweepOrphanMedia::class))
        );
    }

    /**
     * The text values of the nodes an XPath query selects in a module XML file
     *
     * @param string $file
     * @param string $query
     * @return list<string>
     */
    private function values(string $file, string $query): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($file));
        $nodes = (new \DOMXPath($document))->query($query);
        self::assertNotFalse($nodes);
        $values = [];
        foreach ($nodes as $node) {
            $values[] = trim((string)$node->nodeValue);
        }

        return $values;
    }
}
