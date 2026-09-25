<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Console\Command;

use Hryvinskyi\BannerSlider\Model\Media\OrphanMediaSweeper;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `banner-slider:media:cleanup [--dry-run]`: deletes banner slider media files nothing references any more.
 *
 * It always runs when invoked, whatever the scheduled sweep setting says. With `--dry-run` it only lists the files.
 * Building the command touches no service, so every other console command stays cheap.
 */
class CleanupMedia extends Command
{
    public const NAME = 'banner-slider:media:cleanup';
    private const OPTION_DRY_RUN = 'dry-run';

    /**
     * @param OrphanMediaSweeper $sweeper
     * @param string|null $name
     */
    public function __construct(
        private readonly OrphanMediaSweeper $sweeper,
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
            ->setDescription('Delete banner slider media files that no banner, crop or slider references.')
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Only list the files that would be deleted.'
            );
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption(self::OPTION_DRY_RUN) === true;
        try {
            $paths = $this->sweeper->sweep($dryRun);
        } catch (LocalizedException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        foreach ($paths as $path) {
            $output->writeln($path);
        }
        $output->writeln(sprintf(
            $dryRun ? '%d unreferenced file(s) would be deleted.' : '%d unreferenced file(s) deleted.',
            count($paths)
        ));

        return Cli::RETURN_SUCCESS;
    }
}
