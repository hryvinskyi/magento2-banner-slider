<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Repository;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;

/**
 * Saves and deletes a model through its resource model with the repositories' failure rules.
 *
 * A `LocalizedException` (validation, duplicate key, anything already worded for the admin) passes through
 * unchanged. Any other failure is logged with the exception and replaced by the caller's generic message, so no SQL
 * or stack detail reaches the admin.
 */
class EntityPersister
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Save the model
     *
     * @param AbstractDb $resource
     * @param AbstractModel $model
     * @param Phrase $failure Message of the exception thrown for an unexpected failure
     * @return void
     * @throws LocalizedException The resource model's own exception
     * @throws CouldNotSaveException On any other failure
     */
    public function save(AbstractDb $resource, AbstractModel $model, Phrase $failure): void
    {
        try {
            $resource->save($model);
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error($failure->render(), ['exception' => $exception]);
            throw new CouldNotSaveException($failure, $exception instanceof \Exception ? $exception : null);
        }
    }

    /**
     * Delete the model
     *
     * @param AbstractDb $resource
     * @param AbstractModel $model
     * @param Phrase $failure Message of the exception thrown for an unexpected failure
     * @return void
     * @throws LocalizedException The resource model's own exception
     * @throws CouldNotDeleteException On any other failure
     */
    public function delete(AbstractDb $resource, AbstractModel $model, Phrase $failure): void
    {
        try {
            $resource->delete($model);
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error($failure->render(), ['exception' => $exception]);
            throw new CouldNotDeleteException($failure, $exception instanceof \Exception ? $exception : null);
        }
    }
}
