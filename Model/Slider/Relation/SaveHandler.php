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
 * Save handler for slider store and customer group relations
 */
class SaveHandler implements ExtensionInterface
{
    /**
     * Save store and customer group relations for slider
     *
     * @param SliderInterface $entity
     * @param array $arguments
     * @return SliderInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute($entity, $arguments = []): SliderInterface
    {
        $storeIds = $entity->getStoreIds();
        if (is_array($storeIds)) {
            $entity->setStoreIds(implode(',', $storeIds));
        }

        $groupIds = $entity->getCustomerGroupIds();
        if (is_array($groupIds)) {
            $entity->setCustomerGroupIds(implode(',', $groupIds));
        }

        return $entity;
    }
}
