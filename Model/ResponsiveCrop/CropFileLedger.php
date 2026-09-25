<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

/**
 * What one save did to crop files so far: the files it created, the paths its crops now use, and the paths it
 * replaced.
 *
 * It is filled while the save runs, so a failure part-way still knows which files to remove.
 */
class CropFileLedger
{
    /**
     * @var list<string>
     */
    private array $createdPaths = [];

    /**
     * @var list<string>
     */
    private array $newPaths = [];

    /**
     * @var list<string>
     */
    private array $obsoletePaths = [];

    /**
     * Record the files one crop write produced
     *
     * @param CropWriteResult $result
     * @return void
     */
    public function recordWrite(CropWriteResult $result): void
    {
        array_push($this->createdPaths, ...$result->getCreatedPaths());
        array_push($this->newPaths, ...$result->getNewPaths());
        array_push($this->obsoletePaths, ...$result->getObsoletePaths());
    }

    /**
     * Files the save created
     *
     * @return list<string>
     */
    public function getCreatedPaths(): array
    {
        return array_values(array_unique($this->createdPaths));
    }

    /**
     * Paths the saved crops use
     *
     * @return list<string>
     */
    public function getNewPaths(): array
    {
        return array_values(array_unique($this->newPaths));
    }

    /**
     * Paths the save replaced
     *
     * @return list<string>
     */
    public function getObsoletePaths(): array
    {
        return array_values(array_unique($this->obsoletePaths));
    }
}
