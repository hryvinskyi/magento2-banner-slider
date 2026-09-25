<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Media\CommittedMediaRemover;
use Magento\Framework\App\ResourceConnection;

/**
 * Runs a change of crop rows and files as one unit: new files first, rows in a database transaction, old files last.
 *
 * - The work runs inside a transaction and records in a ledger the files it writes and the files it replaces.
 * - On success the transaction commits, and only then are the replaced files removed (those still referenced or
 *   outside the crop output folder stay; a new path equal to an old one is kept). Inside an outer transaction (a data
 *   patch runs in one) the removal waits for the outer commit (see CommittedMediaRemover).
 * - On any failure the transaction rolls back, only the files this run created are removed, and the error is rethrown.
 */
class CropFileTransaction
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param CropFileStore $fileStore
     * @param CommittedMediaRemover $committedMediaRemover
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CropFileStore $fileStore,
        private readonly CommittedMediaRemover $committedMediaRemover
    ) {
    }

    /**
     * Run the work and return its result
     *
     * @template T
     * @param callable(CropFileLedger):T $work
     * @return T
     * @throws \Throwable Whatever the work throws, after the rollback and the removal of the created files
     */
    public function run(callable $work): mixed
    {
        $ledger = new CropFileLedger();
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $result = $work($ledger);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->fileStore->discard($ledger->getCreatedPaths());
            throw $exception;
        }

        $this->committedMediaRemover->removeAfterCommit($ledger->getObsoletePaths(), $ledger->getNewPaths());

        return $result;
    }
}
