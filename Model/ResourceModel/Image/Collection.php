<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel\Image;

use Hryvinskyi\BannerSlider\Model\Image;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Image as ImageResource;
use Hryvinskyi\BannerSliderApi\Api\Data\ImageInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Image collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = ImageInterface::IMAGE_ID;

    /**
     * @var string
     */
    protected $_eventPrefix = 'hryvinskyi_banner_slider_image_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'image_collection';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Image::class, ImageResource::class);
    }

    /**
     * Add banner filter to collection
     *
     * @param int $bannerId
     * @return $this
     */
    public function addBannerFilter(int $bannerId): self
    {
        $this->addFieldToFilter(ImageInterface::BANNER_ID, $bannerId);

        return $this;
    }

    /**
     * Add active filter to collection
     *
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter(ImageInterface::STATUS, 1);

        return $this;
    }
}
