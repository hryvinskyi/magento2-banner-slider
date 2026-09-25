<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Persists breakpoints in `hryvinskyi_banner_slider_breakpoint`; the crops made for one are removed by the database
 * cascade.
 */
class Breakpoint extends AbstractDb
{
    public const TABLE_NAME = 'hryvinskyi_banner_slider_breakpoint';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, BreakpointInterface::BREAKPOINT_ID);
    }
}
