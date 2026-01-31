<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop;

use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop as ResponsiveCropResource;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Responsive crop collection
 */
class Collection extends AbstractCollection
{
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
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResponsiveCrop::class, ResponsiveCropResource::class);
    }

    /**
     * Add banner filter to collection
     *
     * @param int $bannerId
     * @return $this
     */
    public function addBannerFilter(int $bannerId): self
    {
        $this->addFieldToFilter(ResponsiveCropInterface::BANNER_ID, $bannerId);
        return $this;
    }

    /**
     * Add multiple banner IDs filter to collection
     *
     * @param array<int> $bannerIds
     * @return $this
     */
    public function addBannerIdsFilter(array $bannerIds): self
    {
        if (empty($bannerIds)) {
            $this->addFieldToFilter(ResponsiveCropInterface::BANNER_ID, ['null' => true]);
            return $this;
        }

        $this->addFieldToFilter(ResponsiveCropInterface::BANNER_ID, ['in' => $bannerIds]);
        return $this;
    }

    /**
     * Add breakpoint filter to collection
     *
     * @param int $breakpointId
     * @return $this
     */
    public function addBreakpointFilter(int $breakpointId): self
    {
        $this->addFieldToFilter(ResponsiveCropInterface::BREAKPOINT_ID, $breakpointId);
        return $this;
    }

    /**
     * Add active filter to collection
     *
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('main_table.' . ResponsiveCropInterface::STATUS, 1);
        return $this;
    }

    /**
     * Add sort order ordering
     *
     * @param string $direction
     * @return $this
     */
    public function addSortOrderOrdering(string $direction = self::SORT_ORDER_ASC): self
    {
        $this->setOrder(ResponsiveCropInterface::SORT_ORDER, $direction);
        return $this;
    }

    /**
     * Join breakpoint data for media query ordering
     *
     * @return $this
     */
    public function joinBreakpointData(): self
    {
        $this->getSelect()->joinLeft(
            ['breakpoint' => $this->getTable('hryvinskyi_banner_slider_breakpoint')],
            'main_table.breakpoint_id = breakpoint.breakpoint_id',
            ['media_query', 'min_width', 'target_width', 'target_height', 'identifier', 'breakpoint_name' => 'name']
        );

        return $this;
    }
}
