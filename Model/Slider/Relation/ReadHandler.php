<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider\Relation;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\EntityManager\Operation\ExtensionInterface;

/**
 * Read handler for slider store and customer group relations
 *
 * Store IDs and customer group IDs are stored as comma-separated strings
 * in the database and returned as-is. No conversion is needed on read
 * since SliderInterface getters return string type.
 */
class ReadHandler implements ExtensionInterface
{
    /**
     * Load store and customer group relations for slider
     *
     * No-op implementation - relation data is loaded directly from main table columns.
     *
     * @param SliderInterface $entity
     * @param array $arguments
     * @return SliderInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute($entity, $arguments = []): SliderInterface
    {
        return $entity;
    }
}
