<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Psr\Log\LoggerInterface;

/**
 * Crops of banners. Every column is qualified with the main table alias, so callers may join other tables; every
 * loaded crop carries its variants, read in one query for the whole page.
 */
class Collection extends AbstractCollection
{
    /**
     * Data key of the banner's slider id on crops loaded after `joinBannerSliderId()`
     */
    public const BANNER_SLIDER_ID = 'banner_slider_id';

    private const MAIN_TABLE_ALIAS = 'main_table';
    private const BANNER_TABLE_ALIAS = 'banner';

    /**
     * @var string
     */
    protected $_idFieldName = ResponsiveCropInterface::CROP_ID;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_responsive_crop_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'responsive_crop_collection';

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param VariantRows $variantRows
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        private readonly VariantRows $variantRows,
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
        $this->_init(ResponsiveCrop::class, ResponsiveCropResource::class);
    }

    /**
     * Keep crops of the banner
     *
     * @param int $bannerId
     * @return $this
     */
    public function addBannerFilter(int $bannerId): self
    {
        return $this->addBannerIdsFilter([$bannerId]);
    }

    /**
     * Keep crops of any of the banners; an empty list keeps none
     *
     * @param list<int> $bannerIds
     * @return $this
     */
    public function addBannerIdsFilter(array $bannerIds): self
    {
        if ($bannerIds === []) {
            $this->getSelect()->where('1 = 0');

            return $this;
        }
        $this->getSelect()->where($this->mainColumn(ResponsiveCropInterface::BANNER_ID) . ' IN (?)', $bannerIds);

        return $this;
    }

    /**
     * Keep crops made for the breakpoint
     *
     * @param int $breakpointId
     * @return $this
     */
    public function addBreakpointFilter(int $breakpointId): self
    {
        $this->getSelect()->where($this->mainColumn(ResponsiveCropInterface::BREAKPOINT_ID) . ' = ?', $breakpointId);

        return $this;
    }

    /**
     * Keep crops made for any of the breakpoints; an empty list keeps none
     *
     * @param list<int> $breakpointIds
     * @return $this
     */
    public function addBreakpointIdsFilter(array $breakpointIds): self
    {
        if ($breakpointIds === []) {
            $this->getSelect()->where('1 = 0');

            return $this;
        }
        $this->getSelect()->where(
            $this->mainColumn(ResponsiveCropInterface::BREAKPOINT_ID) . ' IN (?)',
            $breakpointIds
        );

        return $this;
    }

    /**
     * Keep crops whose original-format output has been generated
     *
     * @return $this
     */
    public function addGeneratedFilter(): self
    {
        $column = $this->mainColumn(ResponsiveCropInterface::CROPPED_IMAGE);
        $this->getSelect()->where($column . ' IS NOT NULL')->where($column . " <> ''");

        return $this;
    }

    /**
     * Add the slider id of each crop's banner to every loaded crop, under the data key `BANNER_SLIDER_ID`
     *
     * @return $this
     */
    public function joinBannerSliderId(): self
    {
        $this->getSelect()->join(
            [self::BANNER_TABLE_ALIAS => $this->getTable(BannerResource::TABLE_NAME)],
            self::BANNER_TABLE_ALIAS . '.' . BannerInterface::BANNER_ID . ' = '
            . $this->mainColumn(ResponsiveCropInterface::BANNER_ID),
            [self::BANNER_SLIDER_ID => BannerInterface::SLIDER_ID]
        );

        return $this;
    }

    /**
     * Keep enabled crops
     *
     * @return $this
     */
    public function addEnabledFilter(): self
    {
        $this->getSelect()->where($this->mainColumn(ResponsiveCropInterface::STATUS) . ' = ?', 1);

        return $this;
    }

    /**
     * Attach the variants to every loaded crop before its original data is recorded
     *
     * @return $this
     */
    protected function _afterLoad(): self
    {
        $cropIds = [];
        foreach ($this->_items as $item) {
            if ($item instanceof ResponsiveCropInterface && $item->getCropId() !== null) {
                $cropIds[] = $item->getCropId();
            }
        }
        $variants = $this->variantRows->fetchByCropIds($cropIds);
        foreach ($this->_items as $item) {
            if ($item instanceof ResponsiveCropInterface && $item->getCropId() !== null) {
                $item->setVariants($variants[$item->getCropId()] ?? []);
            }
        }

        return parent::_afterLoad();
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
