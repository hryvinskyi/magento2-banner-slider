<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\Media\CommittedMediaRemover;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileLedger;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileStore;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropFileTransaction;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop\CropWriteResult;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropFileTransaction::class)]
class CropFileTransactionTest extends TestCase
{
    /**
     * Every call, in order
     *
     * @var list<string>
     */
    private array $log = [];

    /**
     * @var CropFileTransaction
     */
    private CropFileTransaction $transaction;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        foreach (['beginTransaction', 'commit', 'rollBack'] as $method) {
            $connection->method($method)->willReturnCallback(function () use ($method, $connection) {
                $this->log[] = $method;

                return $connection;
            });
        }
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $fileStore = $this->createMock(CropFileStore::class);
        $fileStore->method('discard')->willReturnCallback(function (array $paths): void {
            $this->log[] = 'discard ' . $this->joined($paths);
        });
        $remover = $this->createMock(CommittedMediaRemover::class);
        $remover->method('removeAfterCommit')->willReturnCallback(function (array $paths, array $keep = []): void {
            $this->log[] = 'remove ' . $this->joined($paths) . ' keeping ' . $this->joined($keep);
        });

        $this->transaction = new CropFileTransaction($resourceConnection, $fileStore, $remover);
    }

    /**
     * Replaced files are removed after the commit, keeping every new path
     *
     * @return void
     */
    public function testCommitThenRemove(): void
    {
        $marker = new \stdClass();
        $result = $this->transaction->run(function (CropFileLedger $ledger) use ($marker): \stdClass {
            $this->log[] = 'work';
            $ledger->recordWrite(new CropWriteResult('r/new.png', [], ['r/new.png'], ['r/old.png']));

            return $marker;
        });

        self::assertSame($marker, $result);
        self::assertSame(
            ['beginTransaction', 'work', 'commit', 'remove r/old.png keeping r/new.png'],
            $this->log
        );
    }

    /**
     * A failure rolls back, removes only the created files and rethrows; nothing obsolete is touched
     *
     * @return void
     */
    public function testRollbackRemovesCreatedFilesOnly(): void
    {
        try {
            $this->transaction->run(function (CropFileLedger $ledger): int {
                $ledger->recordWrite(new CropWriteResult('r/reused.png', [], [], ['r/old.png']));
                $ledger->recordWrite(new CropWriteResult('r/new.png', [], ['r/new.png'], []));

                return $this->failingCropSave();
            });
            self::fail('The failure must be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('crop save failed', $exception->getMessage());
        }

        self::assertSame(['beginTransaction', 'rollBack', 'discard r/new.png'], $this->log);
    }

    /**
     * The strings of a list, comma-separated
     *
     * @param array<mixed> $values
     * @return string
     */
    private function joined(array $values): string
    {
        return implode(',', array_filter($values, 'is_string'));
    }

    /**
     * A crop save that fails
     *
     * @return int
     * @throws \RuntimeException Always
     */
    private function failingCropSave(): int
    {
        throw new \RuntimeException('crop save failed');
    }
}
