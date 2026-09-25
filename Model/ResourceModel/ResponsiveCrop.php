<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResourceModel;

use Hryvinskyi\BannerSlider\Model\ResourceModel\ResponsiveCrop\VariantRows;
use Hryvinskyi\BannerSlider\Model\ResponsiveCrop as ResponsiveCropModel;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * Persists crops: the `hryvinskyi_banner_slider_responsive_crop` row plus its variant rows.
 *
 * Variants are read after load and replaced after save, inside the save transaction, but only when the crop carries
 * them (loaded or set), so saving a crop built without its variants never clears the stored ones.
 */
class ResponsiveCrop extends AbstractDb
{
    public const TABLE_NAME = 'hryvinskyi_banner_slider_responsive_crop';

    /**
     * @param Context $context
     * @param VariantRows $variantRows
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly VariantRows $variantRows,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, ResponsiveCropInterface::CROP_ID);
    }

    /**
     * Attach the crop's variants
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterLoad(AbstractModel $object): self
    {
        if ($object instanceof ResponsiveCropInterface) {
            $cropId = $object->getCropId();
            if ($cropId !== null) {
                $object->setVariants($this->variantRows->fetchByCropIds([$cropId])[$cropId] ?? []);
            }
        }

        return parent::_afterLoad($object);
    }

    /**
     * Replace the variant rows with the crop's variants
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object): self
    {
        if ($object instanceof ResponsiveCropModel && $object->hasVariants()) {
            $cropId = $object->getCropId();
            if ($cropId !== null) {
                $this->variantRows->replace($cropId, $object->getVariants());
            }
        }

        return parent::_afterSave($object);
    }
}
