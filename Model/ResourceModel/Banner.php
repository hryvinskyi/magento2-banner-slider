<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Persists banners in `hryvinskyi_banner_slider_banner`; their crops are removed by the database cascade.
 */
class Banner extends AbstractDb
{
    public const TABLE_NAME = 'hryvinskyi_banner_slider_banner';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, BannerInterface::BANNER_ID);
    }
}
