<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\Slider;

use DateTimeInterface;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ActiveWindowCondition;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider as SliderResource;
use Hryvinskyi\BannerSlider\Model\Slider;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Psr\Log\LoggerInterface;

/**
 * Sliders with the storefront filters: visibility, enabled, active window, location and priority order.
 *
 * Visibility is read from the link tables: a slider is visible in a store view when it has a store row for store 0
 * (all store views) or for that store, and to a customer group when its `all_customer_groups` flag is on or it has a
 * row for that group. A slider without store rows, or with the flag off and no group rows, matches nothing. Every
 * loaded slider carries its store and customer group id lists, read in two queries for the whole page.
 */
class Collection extends AbstractCollection
{
    private const MAIN_TABLE_ALIAS = 'main_table';

    /**
     * @var string
     */
    protected $_idFieldName = SliderInterface::SLIDER_ID;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'slider_collection';

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param VisibilityLinks $visibilityLinks
     * @param ActiveWindowCondition $activeWindowCondition
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        private readonly VisibilityLinks $visibilityLinks,
        private readonly ActiveWindowCondition $activeWindowCondition,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Slider::class, SliderResource::class);
    }

    /**
     * Keep sliders a visitor of the customer group sees on the store view
     *
     * @param int $storeId
     * @param int $customerGroupId
     * @return $this
     */
    public function addVisibilityFilter(int $storeId, int $customerGroupId): self
    {
        return $this->addStoreFilter([$storeId])->addCustomerGroupFilter([$customerGroupId]);
    }

    /**
     * Keep sliders shown on any of the store views (a store row for one of them or for store 0)
     *
     * @param list<int> $storeIds
     * @return $this
     */
    public function addStoreFilter(array $storeIds): self
    {
        $this->getSelect()->where('EXISTS (' . $this->storeLinks($storeIds)->assemble() . ')');

        return $this;
    }

    /**
     * Keep sliders shown on none of the store views: the complement of addStoreFilter()
     *
     * @param list<int> $storeIds
     * @return $this
     */
    public function excludeStores(array $storeIds): self
    {
        $this->getSelect()->where('NOT EXISTS (' . $this->storeLinks($storeIds)->assemble() . ')');

        return $this;
    }

    /**
     * Keep sliders shown to any of the customer groups (the all-groups flag on, or a row for one of them)
     *
     * @param list<int> $customerGroupIds
     * @return $this
     */
    public function addCustomerGroupFilter(array $customerGroupIds): self
    {
        $this->getSelect()->where($this->customerGroupCondition($customerGroupIds));

        return $this;
    }

    /**
     * Keep sliders shown to none of the customer groups: the complement of addCustomerGroupFilter()
     *
     * @param list<int> $customerGroupIds
     * @return $this
     */
    public function excludeCustomerGroups(array $customerGroupIds): self
    {
        $this->getSelect()->where('NOT ' . $this->customerGroupCondition($customerGroupIds));

        return $this;
    }

    /**
     * Keep enabled sliders
     *
     * @return $this
     */
    public function addEnabledFilter(): self
    {
        $this->getSelect()->where($this->mainColumn(SliderInterface::STATUS) . ' = ?', 1);

        return $this;
    }

    /**
     * Keep sliders whose active window contains the moment
     *
     * @param DateTimeInterface $at
     * @return $this
     */
    public function addActiveAtFilter(DateTimeInterface $at): self
    {
        $this->activeWindowCondition->apply($this->getSelect(), $at, self::MAIN_TABLE_ALIAS);

        return $this;
    }

    /**
     * Keep sliders placed at the location code
     *
     * @param string $location
     * @return $this
     */
    public function addLocationFilter(string $location): self
    {
        $this->getSelect()->where($this->mainColumn(SliderInterface::LOCATION) . ' = ?', $location);

        return $this;
    }

    /**
     * Order by priority, lowest value first (lower value = higher priority), then by lowest id
     *
     * @return $this
     */
    public function orderByPriority(): self
    {
        $this->getSelect()
            ->order($this->mainColumn(SliderInterface::PRIORITY) . ' ' . self::SORT_ORDER_ASC)
            ->order($this->mainColumn(SliderInterface::SLIDER_ID) . ' ' . self::SORT_ORDER_ASC);

        return $this;
    }

    /**
     * Attach the store and customer group id lists to every loaded slider before its original data is recorded
     *
     * @return $this
     */
    protected function _afterLoad(): self
    {
        $this->visibilityLinks->attach($this->_items);

        return parent::_afterLoad();
    }

    /**
     * Store link rows of the current slider for any of the store views or store 0
     *
     * @param list<int> $storeIds
     * @return Select
     */
    private function storeLinks(array $storeIds): Select
    {
        return $this->getConnection()->select()
            ->from(['store_link' => $this->getTable(VisibilityLinks::STORE_TABLE)], [new Expression('1')])
            ->where('store_link.' . SliderInterface::SLIDER_ID . ' = ' . $this->mainColumn(SliderInterface::SLIDER_ID))
            ->where('store_link.' . VisibilityLinks::STORE_ID . ' IN (?)', $this->withAllStoreViews($storeIds));
    }

    /**
     * The condition "shown to any of the customer groups", in parentheses
     *
     * @param list<int> $customerGroupIds
     * @return string
     */
    private function customerGroupCondition(array $customerGroupIds): string
    {
        $allGroups = $this->mainColumn(SliderInterface::ALL_CUSTOMER_GROUPS) . ' = 1';
        if ($customerGroupIds === []) {
            return '(' . $allGroups . ')';
        }

        $groupLinks = $this->getConnection()->select()
            ->from(['group_link' => $this->getTable(VisibilityLinks::CUSTOMER_GROUP_TABLE)], [new Expression('1')])
            ->where('group_link.' . SliderInterface::SLIDER_ID . ' = ' . $this->mainColumn(SliderInterface::SLIDER_ID))
            ->where('group_link.' . VisibilityLinks::CUSTOMER_GROUP_ID . ' IN (?)', $customerGroupIds);

        return '(' . $allGroups . ' OR EXISTS (' . $groupLinks->assemble() . '))';
    }

    /**
     * The store ids plus store 0, which stands for all store views
     *
     * @param list<int> $storeIds
     * @return list<int>
     */
    private function withAllStoreViews(array $storeIds): array
    {
        return array_values(array_unique([0, ...$storeIds]));
    }

    /**
     * Column of the main table, qualified with its alias
     *
     * @param string $column
     * @return string
     */
    private function mainColumn(string $column): string
    {
        return self::MAIN_TABLE_ALIAS . '.' . $column;
    }
}
