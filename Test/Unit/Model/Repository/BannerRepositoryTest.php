<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Repository;

use Hryvinskyi\BannerSlider\Model\Banner;
use Hryvinskyi\BannerSlider\Model\Banner\BannerUrlPolicy;
use Hryvinskyi\BannerSlider\Model\BannerFactory;
use Hryvinskyi\BannerSlider\Model\Data\RejectedStoredValueLog;
use Hryvinskyi\BannerSlider\Model\Media\BannerMediaCleaner;
use Hryvinskyi\BannerSlider\Model\Repository\BannerRepository;
use Hryvinskyi\BannerSlider\Model\Repository\EntityPersister;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\CollectionFactory;
use Hryvinskyi\BannerSlider\Test\Unit\Model\EntityModelArguments;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerSearchResultsInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\Validation\BannerValidatorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\AspectRatioParser;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(BannerRepository::class)]
class BannerRepositoryTest extends TestCase
{
    use EntityModelArguments;

    /**
     * @var BannerResource&MockObject
     */
    private MockObject $resource;

    /**
     * @var BannerValidatorInterface&MockObject
     */
    private MockObject $validator;

    /**
     * @var BannerMediaCleaner&MockObject
     */
    private MockObject $mediaCleaner;

    /**
     * @var BannerRepository
     */
    private BannerRepository $repository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->resource = $this->createMock(BannerResource::class);
        $this->validator = $this->createMock(BannerValidatorInterface::class);
        $bannerFactory = $this->createMock(BannerFactory::class);
        $bannerFactory->method('create')->willReturnCallback(fn (): Banner => $this->newBanner());
        $this->mediaCleaner = $this->createMock(BannerMediaCleaner::class);

        $this->repository = new BannerRepository(
            $this->resource,
            $bannerFactory,
            $this->createMock(CollectionFactory::class),
            $this->createMock(BannerSearchResultsInterfaceFactory::class),
            $this->createMock(CollectionProcessorInterface::class),
            $this->validator,
            new EntityPersister($this->createMock(LoggerInterface::class)),
            $this->mediaCleaner
        );
    }

    /**
     * The banner is validated, then stored
     *
     * @return void
     */
    public function testSave(): void
    {
        $banner = $this->newBanner();
        $this->validator->expects(self::once())->method('validate')->with($banner);
        $this->resource->expects(self::once())->method('save')->with($banner);

        self::assertSame($banner, $this->repository->save($banner));
    }

    /**
     * Another implementation of the data interface is refused
     *
     * @return void
     */
    public function testSaveRefusesForeignImplementation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->save($this->createMock(BannerInterface::class));
    }

    /**
     * A missing banner is reported as such
     *
     * @return void
     */
    public function testGetByIdOfMissingBanner(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->repository->getById(99);
    }

    /**
     * A deleted banner's crop folder is removed after the row is gone
     *
     * @return void
     */
    public function testDeleteRemovesCropFolderAfterTheRow(): void
    {
        $banner = $this->newBanner();
        $banner->setBannerId(7);
        $log = [];
        $this->resource->method('delete')->willReturnCallback(function () use (&$log): BannerResource {
            $log[] = 'delete row';

            return $this->resource;
        });
        $this->mediaCleaner->method('removeAfterCommit')->willReturnCallback(
            function (int $bannerId) use (&$log): void {
                $log[] = 'remove files of ' . $bannerId;
            }
        );

        $this->repository->delete($banner);

        self::assertSame(['delete row', 'remove files of 7'], $log);
    }

    /**
     * A failed delete leaves the files alone
     *
     * @return void
     */
    public function testFailedDeleteKeepsFiles(): void
    {
        $banner = $this->newBanner();
        $banner->setBannerId(7);
        $this->resource->method('delete')->willThrowException(new \RuntimeException('database is gone'));
        $this->mediaCleaner->expects(self::never())->method('removeAfterCommit');

        $this->expectException(CouldNotDeleteException::class);
        $this->repository->delete($banner);
    }

    /**
     * A new banner model
     *
     * @return Banner
     */
    private function newBanner(): Banner
    {
        [$context, $registry, $extensionFactory, $attributeFactory] = $this->modelArguments();

        return new Banner(
            $context,
            $registry,
            $extensionFactory,
            $attributeFactory,
            new AspectRatioParser(),
            new BannerUrlPolicy(),
            new RejectedStoredValueLog(new NullLogger()),
            $this->modelResource(BannerInterface::BANNER_ID)
        );
    }
}
