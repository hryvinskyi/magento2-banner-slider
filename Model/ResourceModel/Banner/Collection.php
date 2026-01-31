<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\Banner;

use Hryvinskyi\BannerSlider\Model\Banner;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner as BannerResource;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Banner collection
 */
class Collection extends AbstractCollection
{
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
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Banner::class, BannerResource::class);
    }

    /**
     * Add slider filter to collection
     *
     * @param int $sliderId
     * @return $this
     */
    public function addSliderFilter(int $sliderId): self
    {
        $this->addFieldToFilter(BannerInterface::SLIDER_ID, $sliderId);

        return $this;
    }

    /**
     * Add active filter to collection
     *
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter(BannerInterface::STATUS, 1);

        return $this;
    }

    /**
     * Add date filter to collection
     *
     * @param string|null $date
     * @return $this
     */
    public function addDateFilter(?string $date = null): self
    {
        $date = $date ?? (new \DateTime())->format('Y-m-d H:i:s');

        $this->getSelect()
            ->where('(from_date IS NULL OR from_date <= ?)', $date)
            ->where('(to_date IS NULL OR to_date >= ?)', $date);

        return $this;
    }

    /**
     * Add position sort order
     *
     * @param string $direction
     * @return $this
     */
    public function addPositionOrder(string $direction = 'ASC'): self
    {
        $this->setOrder(BannerInterface::POSITION, $direction);

        return $this;
    }
}
