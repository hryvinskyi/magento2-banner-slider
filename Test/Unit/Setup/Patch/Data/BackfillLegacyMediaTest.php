<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Setup\Patch\Data;

use Hryvinskyi\BannerSlider\Model\Image\ImageInspector;
use Hryvinskyi\BannerSlider\Model\Media\LocalFileWorkspace;
use Hryvinskyi\BannerSlider\Model\Media\ObsoleteMediaRemover;
use Hryvinskyi\BannerSlider\Model\Migration\TypedRowFetcher;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropTargetSize;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\WholeImageCropArea;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\BackfillLegacyMedia;
use Hryvinskyi\BannerSlider\Setup\Patch\Data\NormaliseLegacyRows;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCrop\CropRegeneratorInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\NonTransactionableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(BackfillLegacyMedia::class)]
class BackfillLegacyMediaTest extends TestCase
{
    private const CROP_TABLE = 'hryvinskyi_banner_slider_responsive_crop';
    private const BANNER_A = 'banner_slider/image/a.jpg';
    private const SOURCE_B = 'legacy_slider/image/b.png';

    /**
     * Readable images by media path: [mime, width, height]
     */
    private const IMAGES = [
        'banner_slider/image/a.jpg' => ['image/jpeg', 1600, 800],
        'legacy_slider/image/b.png' => ['image/png', 400, 300],
        'banner_slider/image/text.jpg' => null,
    ];

    /**
     * @var AdapterInterface&MockObject
     */
    private MockObject $connection;

    /**
     * @var LocalFileWorkspace&MockObject
     */
    private MockObject $workspace;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var ObsoleteMediaRemover&MockObject
     */
    private MockObject $obsoleteMediaRemover;

    /**
     * @var CropRegeneratorInterface&MockObject
     */
    private MockObject $cropRegenerator;

    /**
     * Results of the id/value selects, in the order the patch runs them
     *
     * @var list<array<mixed>>
     */
    private array $pairs = [];

    /**
     * Calls in order: transaction steps, row changes, file removal and regeneration
     *
     * @var list<string>
     */
    private array $calls = [];

    /**
     * Regenerations asked for: [banner id, breakpoint id]
     *
     * @var list<array{0: int, 1: int|null}>
     */
    private array $regenerated = [];

    /**
     * Updates made on the connection: [table, data, where]
     *
     * @var list<array{0: string, 1: array<mixed>, 2: mixed}>
     */
    private array $updates = [];

    /**
     * Conditions given to the selects, in order
     *
     * @var list<string>
     */
    private array $conditions = [];

    /**
     * Media paths copied locally, in order
     *
     * @var list<string>
     */
    private array $reads = [];

    /**
     * Warnings logged: [message, context without the exception]
     *
     * @var list<array{0: string, 1: array<mixed>}>
     */
    private array $warnings = [];

    /**
     * Infos logged: [message, context]
     *
     * @var list<array{0: string, 1: array<mixed>}>
     */
    private array $infos = [];

    /**
     * @var BackfillLegacyMedia
     */
    private BackfillLegacyMedia $patch;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnCallback(function (string $condition) use ($select): Select {
            $this->conditions[] = $condition;

            return $select;
        });

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchPairs')->willReturnCallback(fn (): array => array_shift($this->pairs) ?? []);
        $this->connection->method('update')->willReturnCallback(
            function (string $table, array $data, mixed $where): int {
                $this->updates[] = [$table, $data, $where];
                $this->calls[] = 'update';

                return 1;
            }
        );
        $this->connection->method('delete')->willReturnCallback(function (string $table, mixed $where): int {
            $this->calls[] = 'delete ' . $table . ' ' . json_encode($where);

            return 1;
        });
        foreach (['beginTransaction', 'commit', 'rollBack'] as $step) {
            $this->connection->method($step)->willReturnCallback(function () use ($step): AdapterInterface {
                $this->calls[] = $step;

                return $this->connection;
            });
        }
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->willReturnArgument(0);

        $this->workspace = $this->createMock(LocalFileWorkspace::class);
        $this->workspace->method('withLocalCopy')->willReturnCallback(
            function (string $mediaPath, callable $callback): mixed {
                $this->reads[] = $mediaPath;
                if (!array_key_exists($mediaPath, self::IMAGES)) {
                    throw new FileSystemException(__('The media file "%1" does not exist.', $mediaPath));
                }

                return $callback('/tmp/local/' . $mediaPath);
            }
        );
        $inspector = $this->createMock(ImageInspector::class);
        $inspector->method('inspect')->willReturnCallback(function (string $localPath): ?array {
            $image = self::IMAGES[substr($localPath, strlen('/tmp/local/'))] ?? null;

            return $image === null ? null : ['mime' => $image[0], 'width' => $image[1], 'height' => $image[2]];
        });
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->method('warning')->willReturnCallback(
            function (string $message, array $context = []): void {
                $this->warnings[] = [$message, array_diff_key($context, ['exception' => true])];
            }
        );
        $this->logger->method('info')->willReturnCallback(function (string $message, array $context = []): void {
            $this->infos[] = [$message, $context];
        });
        $this->obsoleteMediaRemover = $this->createMock(ObsoleteMediaRemover::class);
        $this->obsoleteMediaRemover->method('remove')->willReturnCallback(function (array $paths): void {
            $this->calls[] = 'remove files ' . json_encode($paths);
        });
        $this->cropRegenerator = $this->createMock(CropRegeneratorInterface::class);

        $this->patch = new BackfillLegacyMedia(
            $setup,
            new TypedRowFetcher(),
            $this->workspace,
            $inspector,
            new WholeImageCropArea(new CropTargetSize()),
            $this->obsoleteMediaRemover,
            $this->cropRegenerator,
            $this->logger
        );
    }

    /**
     * The patch manages its own transaction, so regeneration never runs inside the setup transaction
     *
     * @return void
     */
    public function testIsNotTransactional(): void
    {
        self::assertTrue(
            (new \ReflectionClass(BackfillLegacyMedia::class))->implementsInterface(NonTransactionableInterface::class)
        );
    }

    /**
     * Banner images get their size; whole-image crops of the banner image are removed, those of a dedicated source
     * get its cover area and are regenerated after the commit
     *
     * @return void
     */
    public function testResolvesWholeImageCrops(): void
    {
        $this->pairs = [
            ['1' => 'banner_slider/image/a.jpg'],
            ['40' => 'banner_slider/responsive/2/desktop_old.webp'],
        ];
        $this->connection->method('fetchAll')->willReturn([
            $this->cropRow(5, 1, null, null, [0, 0, 0, 0], 'banner_slider/image/a.jpg', 1920, '294'),
            $this->cropRow(6, 2, self::SOURCE_B, null, [0, 0, 0, 0], self::BANNER_A, 892, null),
            $this->cropRow(7, 1, ' ', null, [0, 0, 0, 0], 'banner_slider/image/a.jpg', 892, '0'),
            $this->cropRow(
                10,
                3,
                'legacy_slider/image/b.png',
                'legacy_slider/image/b.png',
                [0, 0, 892, 588],
                'banner_slider/image/a.jpg',
                892,
                '588'
            ),
            $this->cropRow(
                11,
                2,
                'banner_slider/image/a.jpg',
                'banner_slider/image/a.jpg',
                [0, 0, 1920, 294],
                'banner_slider/image/a.jpg',
                1920,
                '294'
            ),
        ]);
        $this->cropRegenerator->method('regenerate')->willReturnCallback(
            function (int $bannerId, ?int $breakpointId): int {
                $this->regenerated[] = [$bannerId, $breakpointId];
                $this->calls[] = 'regenerate ' . $bannerId;

                return 1;
            }
        );

        self::assertSame($this->patch, $this->patch->apply());

        self::assertSame([], $this->warnings);
        self::assertSame(
            [
                [
                    'hryvinskyi_banner_slider_banner',
                    ['image_width' => 1600, 'image_height' => 800],
                    ['banner_id = ?' => 1],
                ],
                [self::CROP_TABLE, $this->area(0, 0, 400, 300), ['crop_id = ?' => 6]],
                [self::CROP_TABLE, $this->area(0, 18, 400, 264), ['crop_id = ?' => 10]],
            ],
            $this->updates
        );
        self::assertSame(
            [
                'beginTransaction',
                'update',
                'update',
                'update',
                'delete ' . self::CROP_TABLE . ' {"crop_id IN (?)":[5,7,11]}',
                'commit',
                'remove files ["banner_slider\/image\/a.jpg","banner_slider\/responsive\/2\/desktop_old.webp"]',
                'regenerate 2',
                'regenerate 3',
            ],
            $this->calls
        );
        self::assertSame([[2, 4], [3, 4]], $this->regenerated);
        self::assertSame(
            ['banner_slider/image/a.jpg', 'legacy_slider/image/b.png'],
            $this->reads,
            'Each file is read once per run, and the banner image of a removed crop is not read.'
        );
        self::assertContains(
            [
                'Banner slider migration: 3 crops that showed the banner image as it is were removed; the banner '
                . 'image shows instead. 1 crops with an empty area and 1 crops whose output was their own source '
                . 'image got the largest centred area of that source with their breakpoint\'s aspect ratio.',
                [],
            ],
            $this->infos
        );
        self::assertContains(
            ['Banner slider migration: 2 of 2 whole-image crops with a dedicated source were regenerated.', []],
            $this->infos
        );
    }

    /**
     * A crop that cannot be regenerated is logged by crop id; the others still run and the upgrade goes on
     *
     * @return void
     */
    public function testRegenerationFailureIsLoggedByCropId(): void
    {
        $this->connection->method('fetchAll')->willReturn([
            $this->cropRow(6, 2, 'legacy_slider/image/b.png', null, [0, 0, 0, 0], null, 892, null),
            $this->cropRow(8, 3, 'legacy_slider/image/b.png', null, [0, 0, 0, 0], null, 892, null),
        ]);
        $this->cropRegenerator->method('regenerate')->willReturnCallback(function (int $bannerId): int {
            if ($bannerId === 2) {
                throw new CouldNotSaveException(__('Some crops of banner 2 could not be regenerated: disk full'));
            }

            return 1;
        });

        $this->patch->apply();

        self::assertSame(
            [
                [
                    'Banner slider migration: whole-image crops that could not be regenerated keep their earlier '
                    . 'file; fix the cause and run banner-slider:crops:regenerate.',
                    ['reasons_by_crop_id' => [6 => 'Some crops of banner 2 could not be regenerated: disk full']],
                ],
            ],
            $this->warnings
        );
        self::assertContains(
            ['Banner slider migration: 1 of 2 whole-image crops with a dedicated source were regenerated.', []],
            $this->infos
        );
    }

    /**
     * A failure while changing rows rolls the patch's transaction back; nothing is removed or regenerated
     *
     * @return void
     */
    public function testRowFailureRollsBack(): void
    {
        $this->connection->method('fetchAll')->willThrowException(new \RuntimeException('Lock wait timeout'));
        $this->cropRegenerator->expects(self::never())->method('regenerate');
        $this->obsoleteMediaRemover->expects(self::never())->method('remove');

        try {
            $this->patch->apply();
            self::fail('The failure must be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Lock wait timeout', $exception->getMessage());
        }
        self::assertSame(['beginTransaction', 'rollBack'], $this->calls);
    }

    /**
     * An area drawn by hand is left alone, even when the crop's output is the banner image of another source
     *
     * @return void
     */
    public function testLeavesHandDrawnAreasAlone(): void
    {
        $this->connection->method('fetchAll')->willReturn([
            $this->cropRow(
                12,
                1,
                null,
                'banner_slider/responsive/12/desktop_abc.jpg',
                [10, 10, 500, 76],
                'banner_slider/image/a.jpg',
                1920,
                '294'
            ),
            $this->cropRow(
                13,
                1,
                'legacy_slider/image/b.png',
                'banner_slider/image/a.jpg',
                [0, 0, 892, 588],
                'banner_slider/image/a.jpg',
                892,
                '588'
            ),
            $this->cropRow(14, 1, null, null, [0, 0, 892, 588], null, 892, '588'),
        ]);
        $this->workspace->expects(self::never())->method('withLocalCopy');
        $this->cropRegenerator->expects(self::never())->method('regenerate');

        $this->patch->apply();

        self::assertSame([], $this->updates);
        self::assertSame(['beginTransaction', 'commit', 'remove files []'], $this->calls);
    }

    /**
     * Missing and unreadable files are logged by id and skipped; the upgrade goes on
     *
     * @return void
     */
    public function testMissingOrUnreadableFilesAreLoggedAndSkipped(): void
    {
        $this->pairs = [[
            '2' => 'banner_slider/image/missing.jpg',
            '3' => 'banner_slider/image/text.jpg',
        ]];
        $this->connection->method('fetchAll')->willReturn([
            $this->cropRow(8, 1, 'banner_slider/image/gone.png', null, [0, 0, 0, 0], null, 1920, '294'),
            $this->cropRow(
                15,
                1,
                'banner_slider/image/text.jpg',
                'banner_slider/image/text.jpg',
                [0, 0, 1920, 294],
                null,
                1920,
                '294'
            ),
        ]);
        $this->cropRegenerator->expects(self::never())->method('regenerate');

        $this->patch->apply();

        self::assertSame([], $this->updates);
        self::assertContains(
            [
                'Banner slider migration: banner images that could not be read keep no size.',
                ['images_by_banner_id' => [
                    2 => 'banner_slider/image/missing.jpg',
                    3 => 'banner_slider/image/text.jpg',
                ]],
            ],
            $this->warnings
        );
        self::assertContains(
            [
                'Banner slider migration: whole-image crops whose source could not be read keep their stored area '
                . 'and file; copy the source into media and run banner-slider:crops:regenerate.',
                ['sources_by_crop_id' => [
                    8 => 'banner_slider/image/gone.png',
                    15 => 'banner_slider/image/text.jpg',
                ]],
            ],
            $this->warnings
        );
    }

    /**
     * A breakpoint row without a valid target width leaves its crops alone, without reading their source
     *
     * @return void
     */
    public function testCropOfABreakpointWithoutValidSizeIsLoggedAndSkipped(): void
    {
        $this->connection->method('fetchAll')->willReturn([
            $this->cropRow(16, 1, self::SOURCE_B, null, [0, 0, 0, 0], self::BANNER_A, 0, '294'),
        ]);
        $this->workspace->expects(self::never())->method('withLocalCopy');

        $this->patch->apply();

        self::assertSame([], $this->updates);
        self::assertSame(
            [
                [
                    'Banner slider migration: whole-image crops whose breakpoint has no valid size keep their '
                    . 'stored area.',
                    ['reasons_by_crop_id' => [16 => 'Breakpoint spec target width must be greater than 0, got 0.']],
                ],
            ],
            $this->warnings
        );
    }

    /**
     * Only banners without a size are selected; crops are read with their banner image and breakpoint size
     *
     * @return void
     */
    public function testSelectsBannersWithoutSize(): void
    {
        $this->connection->method('fetchAll')->willReturn([]);
        $this->workspace->expects(self::never())->method('withLocalCopy');

        $this->patch->apply();

        self::assertSame(
            ["image IS NOT NULL AND image <> ''", 'image_width IS NULL OR image_height IS NULL'],
            $this->conditions
        );
        self::assertSame([], $this->updates);
    }

    /**
     * It runs once the legacy paths are normalised, and has no aliases
     *
     * @return void
     */
    public function testRunsAfterPathNormalisation(): void
    {
        self::assertSame([NormaliseLegacyRows::class], BackfillLegacyMedia::getDependencies());
        self::assertSame([], $this->patch->getAliases());
    }

    /**
     * A crop row as the patch selects it
     *
     * @param int $cropId
     * @param int $bannerId
     * @param string|null $source
     * @param string|null $output
     * @param list<int> $area x, y, width, height
     * @param string|null $bannerImage
     * @param int $targetWidth
     * @param string|null $targetHeight
     * @return array<string, string|null>
     */
    private function cropRow(
        int $cropId,
        int $bannerId,
        ?string $source,
        ?string $output,
        array $area,
        ?string $bannerImage,
        int $targetWidth,
        ?string $targetHeight
    ): array {
        return [
            'crop_id' => (string)$cropId,
            'banner_id' => (string)$bannerId,
            'breakpoint_id' => '4',
            'source_image' => $source,
            'cropped_image' => $output,
            'crop_x' => (string)$area[0],
            'crop_y' => (string)$area[1],
            'crop_width' => (string)$area[2],
            'crop_height' => (string)$area[3],
            'banner_image' => $bannerImage,
            'identifier' => 'desktop',
            'media_query' => '(min-width: 768px)',
            'min_width' => '768',
            'target_width' => (string)$targetWidth,
            'target_height' => $targetHeight,
        ];
    }

    /**
     * The crop columns of an area
     *
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @return array<string, int>
     */
    private function area(int $x, int $y, int $width, int $height): array
    {
        return ['crop_x' => $x, 'crop_y' => $y, 'crop_width' => $width, 'crop_height' => $height];
    }
}
