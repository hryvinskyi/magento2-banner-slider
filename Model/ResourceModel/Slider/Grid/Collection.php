<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\Grid;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\VisibilityLinks;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * Rows of the admin slider listing, one per slider.
 *
 * Store views and customer groups live in link tables, not in the slider row, so every loaded row gets
 * `store_ids` and `customer_group_ids` int lists attached, one query per link table for the whole page. The listing
 * columns read those keys. Works without any constructor argument besides the framework ones, so it can be injected
 * wherever a framework database collection is expected.
 */
class Collection extends SearchResult
{
    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param VisibilityLinks $visibilityLinks
     * @param string $mainTable
     * @param string|null $resourceModel
     * @param string|null $identifierName
     * @param string|null $connectionName
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        private readonly VisibilityLinks $visibilityLinks,
        string $mainTable = SliderResource::TABLE_NAME,
        ?string $resourceModel = SliderResource::class,
        ?string $identifierName = null,
        ?string $connectionName = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    /**
     * Attach the store and customer group id lists to every loaded row
     *
     * @return $this
     */
    protected function _afterLoad(): self
    {
        $this->visibilityLinks->attach($this->_items);

        return parent::_afterLoad();
    }
}
