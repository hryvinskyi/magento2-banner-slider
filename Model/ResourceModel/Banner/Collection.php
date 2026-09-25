<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\Banner;

use DateTimeInterface;
use Hryvinskyi\BannerSlider\Model\Banner;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ActiveWindowCondition;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Psr\Log\LoggerInterface;

/**
 * Banners with the storefront filters: slider, enabled, active window and slide order.
 */
class Collection extends AbstractCollection
{
    private const MAIN_TABLE_ALIAS = 'main_table';

    /**
     * @var string
     */
    protected $_idFieldName = BannerInterface::BANNER_ID;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_banner_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'banner_collection';

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param ActiveWindowCondition $activeWindowCondition
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
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
        $this->_init(Banner::class, BannerResource::class);
    }

    /**
     * Keep banners of the slider
     *
     * @param int $sliderId
     * @return $this
     */
    public function addSliderFilter(int $sliderId): self
    {
        $this->getSelect()->where($this->mainColumn(BannerInterface::SLIDER_ID) . ' = ?', $sliderId);

        return $this;
    }

    /**
     * Keep enabled banners
     *
     * @return $this
     */
    public function addEnabledFilter(): self
    {
        $this->getSelect()->where($this->mainColumn(BannerInterface::STATUS) . ' = ?', 1);

        return $this;
    }

    /**
     * Keep banners whose active window contains the moment
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
     * Order as slides: position ascending, then id ascending
     *
     * @return $this
     */
    public function orderByPosition(): self
    {
        $this->getSelect()
            ->order($this->mainColumn(BannerInterface::POSITION) . ' ' . self::SORT_ORDER_ASC)
            ->order($this->mainColumn(BannerInterface::BANNER_ID) . ' ' . self::SORT_ORDER_ASC);

        return $this;
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
