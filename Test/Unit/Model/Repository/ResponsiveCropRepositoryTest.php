<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Repository;

use Hryvinskyi\BannerSlider\Model\Media\CommittedMediaRemover;
use Hryvinskyi\BannerSlider\Model\Repository\EntityPersister;
use Hryvinskyi\BannerSlider\Model\Repository\ResponsiveCropRepository;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputFiles;
use Hryvinskyi\BannerSlider\Model\ResponsiveCropFactory;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Validation\ResponsiveCropValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ResponsiveCropRepository::class)]
class ResponsiveCropRepositoryTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var Collection&MockObject
     */
    private MockObject $collection;

    /**
     * @var ResponsiveCropResource&MockObject
     */
    private MockObject $resource;

    /**
     * @var CommittedMediaRemover&MockObject
     */
    private MockObject $committedMediaRemover;

    /**
     * Calls, in order
     *
     * @var list<string>
     */
    private array $log = [];

    /**
     * @var ResponsiveCropRepository
     */
    private ResponsiveCropRepository $repository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->collection);

        $this->resource = $this->createMock(ResponsiveCropResource::class);
        $this->committedMediaRemover = $this->createMock(CommittedMediaRemover::class);
        $this->repository = new ResponsiveCropRepository(
            $this->resource,
            $this->createMock(ResponsiveCropFactory::class),
            $collectionFactory,
            $this->createMock(ResponsiveCropSearchResultsInterfaceFactory::class),
            $this->createMock(CollectionProcessorInterface::class),
            $this->createMock(ResponsiveCropValidatorInterface::class),
            new EntityPersister($this->createMock(LoggerInterface::class)),
            new CropOutputFiles($collectionFactory),
            $this->committedMediaRemover
        );
    }

    /**
     * The crops of a banner, enabled ones only on request
     *
     * @return void
     */
    public function testGetByBannerId(): void
    {
        $crop = $this->newCrop();
        $this->collection->expects(self::once())->method('addBannerFilter')->with(4)->willReturnSelf();
        $this->collection->expects(self::once())->method('addEnabledFilter')->willReturnSelf();
        $this->collection->method('getItems')->willReturn([9 => $crop]);

        self::assertSame([$crop], $this->repository->getByBannerId(4, true));
    }

    /**
     * The crop of a banner for a breakpoint, or null when there is none
     *
     * @return void
     */
    public function testGetByBannerAndBreakpoint(): void
    {
        $crop = $this->newCrop();
        $this->collection->method('addBannerFilter')->with(4)->willReturnSelf();
        $this->collection->method('addBreakpointFilter')->with(2)->willReturnSelf();
        $this->collection->method('setPageSize')->with(1)->willReturnSelf();
        $this->collection->method('getItems')->willReturnOnConsecutiveCalls([9 => $crop], []);

        self::assertSame($crop, $this->repository->getByBannerAndBreakpoint(4, 2));
        self::assertNull($this->repository->getByBannerAndBreakpoint(4, 2));
    }

    /**
     * Another implementation of the data interface is refused
     *
     * @return void
     */
    public function testDeleteRefusesForeignImplementation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->delete($this->createMock(ResponsiveCropInterface::class));
    }

    /**
     * A deleted crop's output files are handed over for removal once the row delete succeeded
     *
     * @return void
     */
    public function testDeleteRemovesOutputFilesAfterTheRow(): void
    {
        $crop = $this->newCrop();
        $crop->setCroppedImage('banner_slider/responsive/4/desktop_a.jpg');
        $this->resource->method('delete')->willReturnCallback(function (): ResponsiveCropResource {
            $this->log[] = 'delete row';

            return $this->resource;
        });
        $this->committedMediaRemover->method('removeAfterCommit')->willReturnCallback(function (array $paths): void {
            $this->log[] = 'remove ' . implode(',', array_filter($paths, 'is_string'));
        });

        $this->repository->delete($crop);

        self::assertSame(['delete row', 'remove banner_slider/responsive/4/desktop_a.jpg'], $this->log);
    }

    /**
     * A failed delete leaves the files alone
     *
     * @return void
     */
    public function testFailedDeleteKeepsFiles(): void
    {
        $crop = $this->newCrop();
        $crop->setCroppedImage('banner_slider/responsive/4/desktop_a.jpg');
        $this->resource->method('delete')->willThrowException(new \RuntimeException('database is gone'));
        $this->committedMediaRemover->expects(self::never())->method('removeAfterCommit');

        $this->expectException(CouldNotDeleteException::class);
        $this->repository->delete($crop);
    }

    /**
     * A new crop model
     *
     * @return ResponsiveCrop
     */
    private function newCrop(): ResponsiveCrop
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();

        return new ResponsiveCrop(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(ResponsiveCropInterface::CROP_ID)
        );
    }
}
