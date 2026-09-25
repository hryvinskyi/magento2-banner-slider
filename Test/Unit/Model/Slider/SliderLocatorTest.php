<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Model\Slider;

use DateTimeImmutable;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\Collection;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Slider\CollectionFactory;
use Hryvinskyi\BannerSlider\Model\Slider\SliderLocator;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\StorefrontContext;
use Magento\Framework\DataObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SliderLocator::class)]
class SliderLocatorTest extends TestCase
{
    /**
     * Collection methods called, in order
     *
     * @var list<string>
     */
    private array $calls = [];

    /**
     * @var Collection&MockObject
     */
    private MockObject $collection;

    /**
     * @var CollectionFactory&MockObject
     */
    private MockObject $collectionFactory;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * Debug messages logged, in order
     *
     * @var list<string>
     */
    private array $debugMessages = [];

    /**
     * @var StorefrontContext
     */
    private StorefrontContext $context;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->context = new StorefrontContext(
            3,
            1,
            new DateTimeImmutable('2026-09-25 10:00:00', new \DateTimeZone('UTC'))
        );
        $this->collection = $this->createMock(Collection::class);
        foreach ([
            'addEnabledFilter',
            'addVisibilityFilter',
            'addActiveAtFilter',
            'addLocationFilter',
            'orderByPriority',
            'addFieldToFilter',
            'setPageSize',
        ] as $method) {
            $this->collection->method($method)->willReturnCallback(function (mixed ...$arguments) use ($method) {
                $this->calls[] = $method . '(' . $this->describe($arguments) . ')';

                return $this->collection;
            });
        }
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionFactory->method('create')->willReturn($this->collection);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * A location lookup keeps qualifying sliders at the location, lowest priority value first, one row
     *
     * @return void
     */
    public function testFindByLocationBuildsTheQuery(): void
    {
        $slider = $this->createMock(SliderInterface::class);
        $this->collection->method('getItems')->willReturn([new DataObject(), $slider]);

        $locator = new SliderLocator($this->collectionFactory, $this->logger);

        self::assertSame($slider, $locator->findByLocation('home-top', $this->context));
        self::assertSame(
            [
                'addEnabledFilter()',
                'addVisibilityFilter(3, 1)',
                'addActiveAtFilter(2026-09-25 10:00:00)',
                'addLocationFilter(home-top)',
                'orderByPriority()',
                'setPageSize(1)',
            ],
            $this->calls
        );
    }

    /**
     * An id lookup applies the same visibility checks to that one slider
     *
     * @return void
     */
    public function testFindByIdBuildsTheQuery(): void
    {
        $this->collection->method('getItems')->willReturn([]);

        self::assertNull((new SliderLocator($this->collectionFactory, $this->logger))->findById(7, $this->context));
        self::assertSame(
            [
                'addEnabledFilter()',
                'addVisibilityFilter(3, 1)',
                'addActiveAtFilter(2026-09-25 10:00:00)',
                'addFieldToFilter(main_table.slider_id, {"eq":7})',
                'setPageSize(1)',
            ],
            $this->calls
        );
    }

    /**
     * A location that is not a valid code, or an id below 1, matches nothing without a query
     *
     * @param string $location
     * @return void
     */
    #[TestWith([''])]
    #[TestWith(['home top'])]
    #[TestWith(['<script>'])]
    public function testInvalidKeysMatchNothing(string $location): void
    {
        $this->collectionFactory->expects(self::never())->method('create');
        $locator = new SliderLocator($this->collectionFactory, $this->logger);

        self::assertNull($locator->findByLocation($location, $this->context));
        self::assertNull($locator->findById(0, $this->context));
    }

    /**
     * An invalid requested location is logged at debug level once per code, however often it is asked for
     *
     * @return void
     */
    public function testInvalidLocationIsLoggedOncePerCode(): void
    {
        $this->logger->expects(self::never())->method('warning');
        $this->logger->method('debug')->willReturnCallback(function (string $message): void {
            $this->debugMessages[] = $message;
        });
        $locator = new SliderLocator($this->collectionFactory, $this->logger);

        $locator->findByLocation('home top', $this->context);
        $locator->findByLocation('home top', $this->context);
        $locator->findByLocation('home.top', $this->context);

        self::assertSame(
            [
                'Banner slider: no slider is looked up for the invalid location "home top".',
                'Banner slider: no slider is looked up for the invalid location "home.top".',
            ],
            $this->debugMessages
        );
    }

    /**
     * After a request reset an invalid location is logged again
     *
     * @return void
     */
    public function testResetLogsInvalidLocationAgain(): void
    {
        $this->logger->expects(self::exactly(2))->method('debug');
        $locator = new SliderLocator($this->collectionFactory, $this->logger);

        $locator->findByLocation('home top', $this->context);
        $locator->_resetState();
        $locator->findByLocation('home top', $this->context);
    }

    /**
     * A readable form of call arguments
     *
     * @param array<mixed> $arguments
     * @return string
     */
    private function describe(array $arguments): string
    {
        return implode(', ', array_map(
            fn (mixed $argument): string => match (true) {
                $argument instanceof DateTimeImmutable => $argument->format('Y-m-d H:i:s'),
                is_array($argument) => (string)json_encode($argument),
                is_scalar($argument) => (string)$argument,
                default => get_debug_type($argument),
            },
            $arguments
        ));
    }
}
