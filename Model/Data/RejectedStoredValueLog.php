<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Data;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Records a stored value a model getter refuses to return because its setter would reject it today.
 *
 * Rows written by earlier versions, or directly in the database, can hold such values; the getter then reads them as
 * empty. Each refusal is logged at debug level once per entity, id and field in a request, so a page that reads the
 * same row many times logs it once.
 */
class RejectedStoredValueLog implements ResetAfterRequestInterface
{
    /**
     * Refusals already logged in this request, by entity, id and field
     *
     * @var array<string,true>
     */
    private array $reported = [];

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Log a refused stored value once
     *
     * @param string $entity Entity name, such as "slider"
     * @param int|null $id Id of the row, null for a row not saved yet
     * @param string $field Column of the value
     * @param string $reason Why the value is refused
     * @return void
     */
    public function report(string $entity, ?int $id, string $field, string $reason): void
    {
        $key = $entity . '#' . ($id ?? 'new') . '#' . $field;
        if (isset($this->reported[$key])) {
            return;
        }
        $this->reported[$key] = true;
        $this->logger->debug(
            sprintf(
                'Banner slider: the stored %s of %s %s is read as empty: %s',
                $field,
                $entity,
                $id === null ? '(not saved)' : (string)$id,
                $reason
            )
        );
    }

    /**
     * Forget what was logged, so the next request logs again
     *
     * @return void
     */
    public function _resetState(): void
    {
        $this->reported = [];
    }
}
