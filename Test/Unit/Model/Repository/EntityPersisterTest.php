<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Repository;

use Hryvinskyi\BannerSlider\Model\Repository\EntityPersister;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(EntityPersister::class)]
class EntityPersisterTest extends TestCase
{
    /**
     * A worded delete failure passes through unchanged and is not logged
     *
     * @return void
     */
    public function testWordedDeleteFailurePassesThrough(): void
    {
        $failure = new LocalizedException(__('Cannot delete a slider in use.'));
        $resource = $this->createMock(AbstractDb::class);
        $resource->method('delete')->willThrowException($failure);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        try {
            (new EntityPersister($logger))
                ->delete($resource, $this->createMock(AbstractModel::class), __('Could not delete.'));
            self::fail('The failure was swallowed.');
        } catch (LocalizedException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /**
     * An engine error is logged and replaced by the generic message
     *
     * @return void
     */
    public function testErrorIsWrapped(): void
    {
        $resource = $this->createMock(AbstractDb::class);
        $resource->method('delete')->willThrowException(new \TypeError('bad'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Could not delete.');

        (new EntityPersister($logger))
            ->delete($resource, $this->createMock(AbstractModel::class), __('Could not delete.'));
    }

    /**
     * A successful save throws nothing and logs nothing
     *
     * @return void
     */
    public function testSave(): void
    {
        $model = $this->createMock(AbstractModel::class);
        $resource = $this->createMock(AbstractDb::class);
        $resource->expects(self::once())->method('save')->with($model);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        (new EntityPersister($logger))->save($resource, $model, __('Could not save.'));
    }

    /**
     * An engine error while saving is logged and wrapped without a previous exception object
     *
     * @return void
     */
    public function testSaveErrorIsWrapped(): void
    {
        $resource = $this->createMock(AbstractDb::class);
        $resource->method('save')->willThrowException(new \Error('engine'));

        try {
            (new EntityPersister($this->createMock(LoggerInterface::class)))
                ->save($resource, $this->createMock(AbstractModel::class), __('Could not save.'));
            self::fail('The failure was swallowed.');
        } catch (CouldNotSaveException $exception) {
            self::assertSame('Could not save.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}
