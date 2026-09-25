<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Magento\Framework\Phrase;

/**
 * The outcome of checking a banner's crop inputs: the changes that passed, and every error found.
 */
class CropChangeSet
{
    /**
     * @param list<CropChange> $changes
     * @param list<Phrase> $errors
     */
    public function __construct(
        private readonly array $changes,
        private readonly array $errors
    ) {
    }

    /**
     * The changes that passed every check
     *
     * @return list<CropChange>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Every error, each naming its breakpoint
     *
     * @return list<Phrase>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
