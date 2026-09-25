<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Slider;

use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\CollectionFactory;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Slider\SliderLocatorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\LocationCode;
use Hryvinskyi\BannerSliderApi\Api\Value\StorefrontContext;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Finds the storefront slider with one query: enabled, visible to the store view and customer group, active at the
 * context's moment, then the lowest priority value and the lowest id.
 *
 * A requested location that is not a valid location code finds nothing. It is logged at debug level once per code
 * and request, so a layout or widget still naming a location an earlier version accepted can be found and fixed.
 */
class SliderLocator implements SliderLocatorInterface, ResetAfterRequestInterface
{
    /**
     * Invalid location codes already logged in this request
     *
     * @var array<string,true>
     */
    private array $reportedInvalidLocations = [];

    /**
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function findByLocation(string $location, StorefrontContext $context): ?SliderInterface
    {
        try {
            $code = new LocationCode($location);
        } catch (\InvalidArgumentException $exception) {
            $this->reportInvalidLocation($location, $exception);

            return null;
        }

        return $this->first($this->qualifying($context)->addLocationFilter($code->getCode())->orderByPriority());
    }

    /**
     * @inheritDoc
     */
    public function findById(int $sliderId, StorefrontContext $context): ?SliderInterface
    {
        if ($sliderId < 1) {
            return null;
        }

        $collection = $this->qualifying($context);
        $collection->addFieldToFilter('main_table.' . SliderInterface::SLIDER_ID, ['eq' => $sliderId]);

        return $this->first($collection);
    }

    /**
     * Sliders a visitor in the context may see
     *
     * @param StorefrontContext $context
     * @return Collection
     */
    private function qualifying(StorefrontContext $context): Collection
    {
        return $this->collectionFactory->create()
            ->addEnabledFilter()
            ->addVisibilityFilter($context->getStoreId(), $context->getCustomerGroupId())
            ->addActiveAtFilter($context->getNow());
    }

    /**
     * The first slider of the collection, loading one row at most
     *
     * @param Collection $collection
     * @return SliderInterface|null
     */
    private function first(Collection $collection): ?SliderInterface
    {
        $collection->setPageSize(1);
        foreach ($collection->getItems() as $item) {
            if ($item instanceof SliderInterface) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Log an invalid requested location once per request
     *
     * @param string $location
     * @param \InvalidArgumentException $exception
     * @return void
     */
    private function reportInvalidLocation(string $location, \InvalidArgumentException $exception): void
    {
        if (isset($this->reportedInvalidLocations[$location])) {
            return;
        }
        $this->reportedInvalidLocations[$location] = true;
        $this->logger->debug(
            sprintf('Banner slider: no slider is looked up for the invalid location "%s".', $location),
            ['reason' => $exception->getMessage()]
        );
    }

    /**
     * Forget what was logged, so the next request logs again
     *
     * @return void
     */
    public function _resetState(): void
    {
        $this->reportedInvalidLocations = [];
    }
}
