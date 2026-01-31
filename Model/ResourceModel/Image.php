<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use Hryvinskyi\BannerSliderApi\Api\Data\ImageInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Image resource model
 */
class Image extends AbstractDb
{
    public const TABLE_NAME = 'hryvinskyi_banner_slider_image';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, ImageInterface::IMAGE_ID);
    }
}
