<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Repository;

use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use Hryvinskyi\BannerSlider\Model\Data\ResponsiveItemsCodec;
use Hryvinskyi\BannerSlider\Model\Media\BannerMediaCleaner;
use Hryvinskyi\BannerSlider\Model\Repository\EntityPersister;
use Hryvinskyi\BannerSlider\Model\Repository\SliderRepository;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\Collection as BannerCollection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\CollectionFactory as BannerCollectionFactory;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\SearchResults\SliderSearchResults;
use Hryvinskyi\BannerSlider\Model\Slider;
use Hryvinskyi\BannerSlider\Model\SliderFactory;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Validation\SliderValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(SliderRepository::class)]
#[CoversClass(EntityPersister::class)]
class SliderRepositoryTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var SliderResource&MockObject
     */
    private MockObject $resource;

    /**
     * @var CollectionFactory&MockObject
     */
    private MockObject $collectionFactory;

    /**
     * @var CollectionProcessorInterface&MockObject
     */
    private MockObject $collectionProcessor;

    /**
     * @var SliderValidatorInterface&MockObject
     */
    private MockObject $validator;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var BannerCollectionFactory&MockObject
     */
    private MockObject $bannerCollectionFactory;

    /**
     * @var BannerMediaCleaner&MockObject
     */
    private MockObject $bannerMediaCleaner;

    /**
     * @var SliderRepository
     */
    private SliderRepository $repository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->resource = $this->createMock(SliderResource::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionProcessor = $this->createMock(CollectionProcessorInterface::class);
        $this->validator = $this->createMock(SliderValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->bannerCollectionFactory = $this->createMock(BannerCollectionFactory::class);
        $this->bannerMediaCleaner = $this->createMock(BannerMediaCleaner::class);

        $sliderFactory = $this->createMock(SliderFactory::class);
        $sliderFactory->method('create')->willReturnCallback(fn (): Slider => $this->newSlider());
        $searchResultsFactory = $this->createMock(SliderSearchResultsInterfaceFactory::class);
        $searchResultsFactory->method('create')->willReturnCallback(
            fn (): SliderSearchResults => new SliderSearchResults($this->createMock(SearchCriteriaFactory::class))
        );

        $this->repository = new SliderRepository(
            $this->resource,
            $sliderFactory,
            $this->collectionFactory,
            $searchResultsFactory,
            $this->collectionProcessor,
            $this->validator,
            new EntityPersister($this->logger),
            $this->bannerCollectionFactory,
            $this->bannerMediaCleaner
        );
    }

    /**
     * The slider is validated, then stored, and returned
     *
     * @return void
     */
    public function testSaveValidatesThenStores(): void
    {
        $calls = [];
        $slider = $this->newSlider();
        $this->validator->expects(self::once())->method('validate')->with($slider)
            ->willReturnCallback(function () use (&$calls): void {
                $calls[] = 'validate';
            });
        $this->resource->expects(self::once())->method('save')->with($slider)
            ->willReturnCallback(function () use (&$calls): SliderResource {
                $calls[] = 'save';

                return $this->resource;
            });

        self::assertSame($slider, $this->repository->save($slider));
        self::assertSame(['validate', 'save'], $calls);
    }

    /**
     * Another implementation of the data interface is refused before anything runs
     *
     * @return void
     */
    public function testSaveRefusesForeignImplementation(): void
    {
        $this->validator->expects(self::never())->method('validate');
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->save($this->createMock(SliderInterface::class));
    }

    /**
     * A validation failure reaches the caller unchanged and nothing is stored
     *
     * @return void
     */
    public function testValidationFailureIsNotWrapped(): void
    {
        $failure = new ValidationException(__('Invalid.'));
        $this->validator->method('validate')->willThrowException($failure);
        $this->resource->expects(self::never())->method('save');

        try {
            $this->repository->save($this->newSlider());
            self::fail('The validation failure was swallowed.');
        } catch (ValidationException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /**
     * A duplicate key reaches the caller unchanged
     *
     * @return void
     */
    public function testAlreadyExistsIsNotWrapped(): void
    {
        $failure = new AlreadyExistsException(__('Duplicate.'));
        $this->resource->method('save')->willThrowException($failure);

        try {
            $this->repository->save($this->newSlider());
            self::fail('The duplicate key failure was swallowed.');
        } catch (AlreadyExistsException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /**
     * Any other failure is logged and replaced by a generic message without the SQL text
     *
     * @return void
     */
    public function testUnexpectedSaveFailureIsLoggedAndWrapped(): void
    {
        $failure = new \RuntimeException('SQLSTATE[42S22]: Unknown column');
        $this->resource->method('save')->willThrowException($failure);
        $this->logger->expects(self::once())->method('error')
            ->with('The slider could not be saved.', ['exception' => $failure]);

        try {
            $this->repository->save($this->newSlider());
            self::fail('The failure was swallowed.');
        } catch (CouldNotSaveException $exception) {
            self::assertSame('The slider could not be saved.', $exception->getMessage());
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    /**
     * A missing slider is reported as such
     *
     * @return void
     */
    public function testGetByIdOfMissingSlider(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->repository->getById(99);
    }

    /**
     * A loaded slider is returned; nothing is cached between calls
     *
     * @return void
     */
    public function testGetByIdLoadsEveryTime(): void
    {
        $this->resource->expects(self::exactly(2))->method('load')->willReturnCallback(
            function (AbstractModel $model, mixed $value): SliderResource {
                $model->setData(SliderInterface::SLIDER_ID, $value);

                return $this->resource;
            }
        );

        self::assertSame(4, $this->repository->getById(4)->getSliderId());
        self::assertSame(4, $this->repository->getById(4)->getSliderId());
    }

    /**
     * The search runs through the collection processor and keeps only sliders
     *
     * @return void
     */
    public function testGetList(): void
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $slider = $this->newSlider();
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([$slider, new DataObject()]);
        $collection->method('getSize')->willReturn(7);
        $this->collectionFactory->method('create')->willReturn($collection);
        $this->collectionProcessor->expects(self::once())->method('process')->with($criteria, $collection);

        $results = $this->repository->getList($criteria);

        self::assertSame([$slider], $results->getItems());
        self::assertSame(7, $results->getTotalCount());
        self::assertSame($criteria, $results->getSearchCriteria());
    }

    /**
     * A delete failure is logged and replaced by a generic message
     *
     * @return void
     */
    public function testDeleteFailureIsWrapped(): void
    {
        $this->resource->method('delete')->willThrowException(new \RuntimeException('Lock wait timeout'));
        $this->logger->expects(self::once())->method('error');
        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('The slider could not be deleted.');

        $this->repository->delete($this->newSlider());
    }

    /**
     * The banners' crop folders are removed after the delete, for the banner ids read before it
     *
     * @return void
     */
    public function testDeleteRemovesCropFoldersOfTheSliderBannersAfterwards(): void
    {
        $calls = [];
        $slider = $this->newSlider();
        $slider->setSliderId(8);
        $banners = $this->createMock(BannerCollection::class);
        $banners->expects(self::once())->method('addSliderFilter')->with(8)->willReturnSelf();
        $banners->method('getAllIds')->willReturnCallback(function () use (&$calls): array {
            $calls[] = 'read banner ids';

            return ['3', 5, 'x'];
        });
        $this->bannerCollectionFactory->method('create')->willReturn($banners);
        $this->resource->expects(self::once())->method('delete')->with($slider)
            ->willReturnCallback(function () use (&$calls): SliderResource {
                $calls[] = 'delete';

                return $this->resource;
            });
        $this->bannerMediaCleaner->expects(self::exactly(2))->method('removeAfterCommit')
            ->willReturnCallback(function (int $bannerId) use (&$calls): void {
                $calls[] = 'clean ' . $bannerId;
            });

        $this->repository->delete($slider);

        self::assertSame(['read banner ids', 'delete', 'clean 3', 'clean 5'], $calls);
    }

    /**
     * A failed delete removes no crop folder
     *
     * @return void
     */
    public function testFailedDeleteRemovesNoCropFolder(): void
    {
        $slider = $this->newSlider();
        $slider->setSliderId(8);
        $banners = $this->createMock(BannerCollection::class);
        $banners->method('addSliderFilter')->willReturnSelf();
        $banners->method('getAllIds')->willReturn([3]);
        $this->bannerCollectionFactory->method('create')->willReturn($banners);
        $this->resource->method('delete')->willThrowException(new \RuntimeException('Lock wait timeout'));
        $this->bannerMediaCleaner->expects(self::never())->method('removeAfterCommit');
        $this->expectException(CouldNotDeleteException::class);

        $this->repository->delete($slider);
    }

    /**
     * A slider without an id has no banners to look up
     *
     * @return void
     */
    public function testDeleteOfUnsavedSliderLooksUpNoBanners(): void
    {
        $this->bannerCollectionFactory->expects(self::never())->method('create');
        $this->bannerMediaCleaner->expects(self::never())->method('removeAfterCommit');

        $this->repository->delete($this->newSlider());
    }

    /**
     * Deleting by id loads the slider and deletes it
     *
     * @return void
     */
    public function testDeleteById(): void
    {
        $banners = $this->createMock(BannerCollection::class);
        $banners->method('addSliderFilter')->with(6)->willReturnSelf();
        $banners->method('getAllIds')->willReturn([]);
        $this->bannerCollectionFactory->method('create')->willReturn($banners);
        $this->resource->method('load')->willReturnCallback(
            function (AbstractModel $model, mixed $value): SliderResource {
                $model->setData(SliderInterface::SLIDER_ID, $value);

                return $this->resource;
            }
        );
        $this->resource->expects(self::once())->method('delete')->with(
            self::callback(fn (Slider $slider): bool => $slider->getSliderId() === 6)
        );

        $this->repository->deleteById(6);
    }

    /**
     * A new slider model
     *
     * @return Slider
     */
    private function newSlider(): Slider
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();

        return new Slider(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            new ResponsiveItemsCodec(),
            new RejectedStoredValueLog(new NullLogger()),
            $this->modelResource(SliderInterface::SLIDER_ID)
        );
    }
}
