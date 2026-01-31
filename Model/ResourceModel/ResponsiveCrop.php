<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Responsive crop resource model
 */
class ResponsiveCrop extends AbstractDb
{
    public const TABLE_NAME = 'hryvinskyi_banner_slider_responsive_crop';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, ResponsiveCropInterface::CROP_ID);
    }
}
