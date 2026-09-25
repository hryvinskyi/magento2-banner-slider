<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Test\Unit\Console\Command;

use Hryvinskyi\BannerSlider\Console\Command\RegenerateCrops;
use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerSearchResultsInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCrop\CropRegeneratorInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(RegenerateCrops::class)]
class RegenerateCropsTest extends TestCase
{
    /**
     * @var CropRegeneratorInterface&MockObject
     */
    private MockObject $regenerator;

    /**
     * @var BannerRepositoryInterface&MockObject
     */
    private MockObject $bannerRepository;

    /**
     * @var SearchCriteriaBuilder&MockObject
     */
    private MockObject $searchCriteriaBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->regenerator = $this->createMock(CropRegeneratorInterface::class);
        $this->bannerRepository = $this->createMock(BannerRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
    }

    /**
     * Building the command and reading its definition call no service
     *
     * @return void
     */
    public function testConstructionIsCheap(): void
    {
        $this->regenerator->expects(self::never())->method(self::anything());
        $this->bannerRepository->expects(self::never())->method(self::anything());
        $this->searchCriteriaBuilder->expects(self::never())->method(self::anything());

        $command = $this->command();

        self::assertSame('banner-slider:crops:regenerate', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('banner'));
        self::assertTrue($command->getDefinition()->hasOption('breakpoint'));
    }

    /**
     * One banner, optionally one breakpoint
     *
     * @return void
     */
    public function testOneBanner(): void
    {
        $this->bannerRepository->expects(self::never())->method('getList');
        $this->regenerator->expects(self::once())->method('regenerate')->with(5, 3)->willReturn(1);
        $tester = new CommandTester($this->command());

        self::assertSame(Cli::RETURN_SUCCESS, $tester->execute(['--banner' => '5', '--breakpoint' => '3']));
        self::assertSame("Banner 5: 1 crop(s) regenerated.\n1 crop(s) regenerated.\n", $tester->getDisplay());
    }

    /**
     * Without --banner every banner is processed; a failing one does not stop the others but fails the run
     *
     * @return void
     */
    public function testAllBanners(): void
    {
        $criteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->method('create')->willReturn($criteria);
        $results = $this->createMock(BannerSearchResultsInterface::class);
        $results->method('getItems')->willReturn([$this->banner(9), $this->banner(2), $this->banner(4)]);
        $this->bannerRepository->method('getList')->with($criteria)->willReturn($results);
        $this->regenerator->method('regenerate')->willReturnCallback(
            static function (int $bannerId, ?int $breakpointId): int {
                self::assertNull($breakpointId);
                if ($bannerId === 4) {
                    throw new CouldNotSaveException(__('Some crops of banner 4 could not be regenerated: x'));
                }

                return $bannerId;
            }
        );
        $tester = new CommandTester($this->command());

        self::assertSame(Cli::RETURN_FAILURE, $tester->execute([]));
        self::assertSame(
            "Banner 2: 2 crop(s) regenerated.\n"
            . "Banner 4: Some crops of banner 4 could not be regenerated: x\n"
            . "Banner 9: 9 crop(s) regenerated.\n"
            . "11 crop(s) regenerated.\n",
            $tester->getDisplay()
        );
    }

    /**
     * An unknown banner is reported and fails the run
     *
     * @return void
     */
    public function testUnknownBanner(): void
    {
        $this->regenerator->method('regenerate')->willThrowException(
            new NoSuchEntityException(__('The banner with id "%1" does not exist.', 77))
        );
        $tester = new CommandTester($this->command());

        self::assertSame(Cli::RETURN_FAILURE, $tester->execute(['--banner' => '77']));
        self::assertStringContainsString('The banner with id "77" does not exist.', $tester->getDisplay());
    }

    /**
     * An id option that is not a positive whole number is refused before anything runs
     *
     * @param string $option
     * @param string $value
     * @return void
     */
    #[TestWith(['--banner', 'abc'])]
    #[TestWith(['--banner', '0'])]
    #[TestWith(['--breakpoint', '-3'])]
    #[TestWith(['--breakpoint', '1.5'])]
    public function testInvalidOption(string $option, string $value): void
    {
        $this->regenerator->expects(self::never())->method('regenerate');
        $tester = new CommandTester($this->command());

        self::assertSame(Cli::RETURN_FAILURE, $tester->execute([$option => $value]));
        self::assertStringContainsString('take a positive whole number', $tester->getDisplay());
    }

    /**
     * The command over the doubles
     *
     * @return RegenerateCrops
     */
    private function command(): RegenerateCrops
    {
        return new RegenerateCrops($this->regenerator, $this->bannerRepository, $this->searchCriteriaBuilder);
    }

    /**
     * A banner with an id
     *
     * @param int $bannerId
     * @return BannerInterface
     */
    private function banner(int $bannerId): BannerInterface
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getBannerId')->willReturn($bannerId);

        return $banner;
    }
}
