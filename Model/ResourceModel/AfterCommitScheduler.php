<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;

/**
 * Runs work that must only happen once the package's rows are committed, such as deleting the files they used.
 *
 * Outside a transaction the work runs at once. Inside one (a save of several rows, a delete inside a caller's
 * transaction, or a data patch that runs in the setup transaction) it waits for the outermost commit, through the
 * framework's commit callbacks, and it is dropped when the transaction rolls back, so a rolled-back change never
 * loses the files its restored rows point at. Every package table is written on the same connection, the crop
 * resource's.
 */
class AfterCommitScheduler
{
    /**
     * @param ResponsiveCropResource $resource A package resource, whose connection the rows are written on
     */
    public function __construct(
        private readonly ResponsiveCropResource $resource
    ) {
    }

    /**
     * Run the work now, or after the outermost commit when a transaction is open
     *
     * @param callable():void $work
     * @return void
     */
    public function schedule(callable $work): void
    {
        $connection = $this->resource->getConnection();
        if ($connection !== false && $connection->getTransactionLevel() > 0) {
            $this->resource->addCommitCallback($work);

            return;
        }

        $work();
    }
}
