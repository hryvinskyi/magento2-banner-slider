<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Repository;

use Hryvinskyi\BannerSlider\Model\Breakpoint;
use Hryvinskyi\BannerSlider\Model\BreakpointFactory;
use Hryvinskyi\BannerSlider\Model\Media\CommittedMediaRemover;
use Hryvinskyi\BannerSlider\Model\Repository\BreakpointRepository;
use Hryvinskyi\BannerSlider\Model\Repository\EntityPersister;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint as BreakpointResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Breakpoint\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropOutputFiles;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Validation\BreakpointValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\DataObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(BreakpointRepository::class)]
class BreakpointRepositoryTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var Collection&MockObject
     */
    private MockObject $collection;

    /**
     * @var BreakpointResource&MockObject
     */
    private MockObject $resource;

    /**
     * @var CropOutputFiles&MockObject
     */
    private MockObject $cropOutputFiles;

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
     * @var BreakpointRepository
     */
    private BreakpointRepository $repository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->collection);

        $this->resource = $this->createMock(BreakpointResource::class);
        $this->cropOutputFiles = $this->createMock(CropOutputFiles::class);
        $this->committedMediaRemover = $this->createMock(CommittedMediaRemover::class);
        $this->repository = new BreakpointRepository(
            $this->resource,
            $this->createMock(BreakpointFactory::class),
            $collectionFactory,
            $this->createMock(BreakpointSearchResultsInterfaceFactory::class),
            $this->createMock(CollectionProcessorInterface::class),
            $this->createMock(BreakpointValidatorInterface::class),
            new EntityPersister($this->createMock(LoggerInterface::class)),
            $this->cropOutputFiles,
            $this->committedMediaRemover
        );
    }

    /**
     * The breakpoints of a slider come in rendering order, optionally enabled ones only
     *
     * @param bool $enabledOnly
     * @return void
     */
    #[TestWith([true])]
    #[TestWith([false])]
    public function testGetBySliderId(bool $enabledOnly): void
    {
        $breakpoint = $this->newBreakpoint();
        $this->collection->expects(self::once())->method('addSliderFilter')->with(3)->willReturnSelf();
        $this->collection->expects($enabledOnly ? self::once() : self::never())
            ->method('addEnabledFilter')->willReturnSelf();
        $this->collection->expects(self::once())->method('orderForRendering')->willReturnSelf();
        $this->collection->method('getItems')->willReturn([5 => $breakpoint, 6 => new DataObject()]);

        self::assertSame([$breakpoint], $this->repository->getBySliderId(3, $enabledOnly));
    }

    /**
     * The output files of the deleted breakpoint's crops are read before the row goes (its crops cascade with it) and
     * handed over for removal once the delete succeeded
     *
     * @return void
     */
    public function testDeleteRemovesCropFilesAfterTheRow(): void
    {
        $breakpoint = $this->newBreakpoint();
        $breakpoint->setBreakpointId(8);
        $this->cropOutputFiles->method('ofBreakpoints')->willReturnCallback(function (array $ids): array {
            $this->log[] = 'read files of ' . implode(',', array_filter($ids, 'is_int'));

            return ['banner_slider/responsive/4/desktop_a.jpg'];
        });
        $this->resource->method('delete')->willReturnCallback(function (): BreakpointResource {
            $this->log[] = 'delete row';

            return $this->resource;
        });
        $this->committedMediaRemover->method('removeAfterCommit')->willReturnCallback(function (array $paths): void {
            $this->log[] = 'remove ' . implode(',', array_filter($paths, 'is_string'));
        });

        $this->repository->delete($breakpoint);

        self::assertSame(
            ['read files of 8', 'delete row', 'remove banner_slider/responsive/4/desktop_a.jpg'],
            $this->log
        );
    }

    /**
     * A failed delete leaves the files alone
     *
     * @return void
     */
    public function testFailedDeleteKeepsFiles(): void
    {
        $breakpoint = $this->newBreakpoint();
        $breakpoint->setBreakpointId(8);
        $this->cropOutputFiles->method('ofBreakpoints')->willReturn(['banner_slider/responsive/4/desktop_a.jpg']);
        $this->resource->method('delete')->willThrowException(new \RuntimeException('database is gone'));
        $this->committedMediaRemover->expects(self::never())->method('removeAfterCommit');

        $this->expectException(CouldNotDeleteException::class);
        $this->repository->delete($breakpoint);
    }

    /**
     * A new breakpoint model
     *
     * @return Breakpoint
     */
    private function newBreakpoint(): Breakpoint
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();

        return new Breakpoint(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            $this->modelResource(BreakpointInterface::BREAKPOINT_ID)
        );
    }
}
