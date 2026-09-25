<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Registry;

/**
 * The framework arguments every entity model constructor starts with, as test doubles.
 */
trait EntityModelArguments
{
    /**
     * Context, registry, extension attributes factory and custom attribute factory doubles
     *
     * @return array{0: Context, 1: Registry, 2: ExtensionAttributesFactory, 3: AttributeValueFactory}
     */
    private function modelArguments(): array
    {
        return [
            $this->createMock(Context::class),
            $this->createMock(Registry::class),
            $this->createMock(ExtensionAttributesFactory::class),
            $this->createMock(AttributeValueFactory::class),
        ];
    }

    /**
     * Resource model double naming the entity's id field, so the model never asks the object manager for one
     *
     * @param string $idFieldName
     * @return AbstractDb
     */
    private function modelResource(string $idFieldName): AbstractDb
    {
        $resource = $this->createMock(AbstractDb::class);
        $resource->method('getIdFieldName')->willReturn($idFieldName);

        return $resource;
    }
}
