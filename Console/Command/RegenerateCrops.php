<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Console\Command;

use Hryvinskyi\BannerSliderApi\Api\BannerRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCrop\CropRegeneratorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `banner-slider:crops:regenerate [--banner=ID] [--breakpoint=ID]`: re-encodes stored crops on the server.
 *
 * Without `--banner` every banner is processed; `--breakpoint` limits each banner to its crop for that breakpoint. A
 * banner that fails does not stop the others; the command then ends with a failure code. Building the command touches
 * no service, so every other console command stays cheap.
 */
class RegenerateCrops extends Command
{
    public const NAME = 'banner-slider:crops:regenerate';
    private const OPTION_BANNER = 'banner';
    private const OPTION_BREAKPOINT = 'breakpoint';

    /**
     * @param CropRegeneratorInterface $regenerator
     * @param BannerRepositoryInterface $bannerRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param string|null $name
     */
    public function __construct(
        private readonly CropRegeneratorInterface $regenerator,
        private readonly BannerRepositoryInterface $bannerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName(self::NAME)
            ->setDescription('Re-encode banner crops on the server from their source image and crop area.')
            ->addOption(
                self::OPTION_BANNER,
                null,
                InputOption::VALUE_REQUIRED,
                'Only this banner id; all banners when omitted.'
            )
            ->addOption(
                self::OPTION_BREAKPOINT,
                null,
                InputOption::VALUE_REQUIRED,
                'Only the crops for this breakpoint id.'
            );
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $bannerId = $this->idOption($input, self::OPTION_BANNER);
        $breakpointId = $this->idOption($input, self::OPTION_BREAKPOINT);
        if ($bannerId === false || $breakpointId === false) {
            $output->writeln('<error>--banner and --breakpoint take a positive whole number.</error>');

            return Cli::RETURN_FAILURE;
        }

        $failed = false;
        $total = 0;
        foreach ($bannerId === null ? $this->allBannerIds() : [$bannerId] as $id) {
            try {
                $count = $this->regenerator->regenerate($id, $breakpointId);
                $total += $count;
                $output->writeln(sprintf('Banner %d: %d crop(s) regenerated.', $id, $count));
            } catch (LocalizedException $exception) {
                $failed = true;
                $output->writeln(sprintf('<error>Banner %d: %s</error>', $id, $exception->getMessage()));
            }
        }
        $output->writeln(sprintf('%d crop(s) regenerated.', $total));

        return $failed ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }

    /**
     * An id option: null when omitted, false when it is not a positive whole number
     *
     * @param InputInterface $input
     * @param string $name
     * @return int|false|null
     */
    private function idOption(InputInterface $input, string $name): int|false|null
    {
        $value = $input->getOption($name);
        if ($value === null) {
            return null;
        }

        return is_string($value) && preg_match('/^[1-9]\d{0,9}$/', $value) === 1 ? (int)$value : false;
    }

    /**
     * Every banner id, ascending
     *
     * @return list<int>
     */
    private function allBannerIds(): array
    {
        $ids = [];
        foreach ($this->bannerRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $banner) {
            $id = $banner->getBannerId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        sort($ids);

        return $ids;
    }
}
