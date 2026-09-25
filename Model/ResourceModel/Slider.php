<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\VisibilityLinks;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * Persists sliders: the `hryvinskyi_banner_slider` row plus its store view and customer group link rows.
 *
 * The link rows are read after load and replaced after save, inside the save transaction. They are replaced only
 * when the slider carries its store list (loaded or set through its visibility), so saving a slider built without
 * one never clears the stored links.
 */
class Slider extends AbstractDb
{
    public const TABLE_NAME = 'hryvinskyi_banner_slider';

    /**
     * @param Context $context
     * @param VisibilityLinks $visibilityLinks
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly VisibilityLinks $visibilityLinks,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, SliderInterface::SLIDER_ID);
    }

    /**
     * Attach the store view and customer group id lists
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterLoad(AbstractModel $object): self
    {
        $this->visibilityLinks->attach([$object]);

        return parent::_afterLoad($object);
    }

    /**
     * Replace the link rows with the slider's visibility
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object): self
    {
        if ($object instanceof SliderInterface && $object->hasData(SliderInterface::STORE_IDS)) {
            $sliderId = $object->getSliderId();
            if ($sliderId !== null) {
                $this->visibilityLinks->replace($sliderId, $object->getVisibility());
            }
        }

        return parent::_afterSave($object);
    }
}
