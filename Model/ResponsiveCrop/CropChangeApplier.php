<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterfaceFactory;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Phrase;

/**
 * Applies checked crop changes to a saved banner: first every new file, then every crop row.
 *
 * Writing all files before any row changes keeps the rows of one save pointing either at the old files or at the
 * new ones. Every file written is recorded in the ledger as it happens, so a caller that rolls back knows exactly what
 * to remove. A deleted crop's files are removed by the crop repository once the deletion is committed. The changes
 * were checked beforehand (see CropChangeValidator), so applying them fails only on storage. Call it inside a
 * transaction (see CropFileTransaction).
 */
class CropChangeApplier
{
    /**
     * @param CropWriter $cropWriter
     * @param ResponsiveCropRepositoryInterface $cropRepository
     * @param ResponsiveCropInterfaceFactory $cropFactory
     */
    public function __construct(
        private readonly CropWriter $cropWriter,
        private readonly ResponsiveCropRepositoryInterface $cropRepository,
        private readonly ResponsiveCropInterfaceFactory $cropFactory
    ) {
    }

    /**
     * Write the files and save or delete the crops
     *
     * @param list<CropChange> $changes
     * @param BannerInterface $banner The saved banner, with its id
     * @param CropFileLedger $ledger
     * @return int Number of crops saved (removed crops are not counted)
     * @throws CouldNotSaveException When a file or a crop cannot be stored, or a crop cannot be deleted
     */
    public function apply(array $changes, BannerInterface $banner, CropFileLedger $ledger): int
    {
        $bannerId = $banner->getBannerId();
        if ($bannerId === null) {
            throw new CouldNotSaveException(__('The crops of a banner cannot be saved before the banner is.'));
        }

        $results = [];
        foreach ($changes as $index => $change) {
            if ($change->getInput()->shouldRemove()) {
                continue;
            }
            $output = $change->getOutput();
            if ($output === null) {
                throw new CouldNotSaveException(__(
                    'The crop for breakpoint "%1" has no source image.',
                    $change->getBreakpoint()->getIdentifier()
                ));
            }
            $results[$index] = $this->cropWriter->write(
                $change->getCurrent(),
                $output,
                $bannerId,
                $change->getBreakpoint()->getIdentifier()
            );
            $ledger->recordWrite($results[$index]);
        }

        $saved = 0;
        foreach ($changes as $index => $change) {
            if (!isset($results[$index])) {
                $this->remove($change);
                continue;
            }
            $this->cropRepository->save($this->updatedCrop($change, $bannerId, $results[$index]));
            $saved++;
        }

        return $saved;
    }

    /**
     * Delete stored crops that no longer apply to the banner; their files go once the deletion is committed
     *
     * @param list<ResponsiveCropInterface> $crops
     * @return void
     * @throws CouldNotSaveException When a crop cannot be deleted
     */
    public function release(array $crops): void
    {
        foreach ($crops as $crop) {
            $this->delete(
                $crop,
                __(
                    'The crop for breakpoint %1 that no longer applies could not be deleted.',
                    (int)$crop->getBreakpointId()
                )
            );
        }
    }

    /**
     * Delete the stored crop of a removal; nothing to do when there is none
     *
     * @param CropChange $change
     * @return void
     * @throws CouldNotSaveException When the crop cannot be deleted
     */
    private function remove(CropChange $change): void
    {
        $current = $change->getCurrent();
        if ($current === null) {
            return;
        }
        $this->delete(
            $current,
            __('The crop for breakpoint "%1" could not be deleted.', $change->getBreakpoint()->getIdentifier())
        );
    }

    /**
     * Delete a stored crop
     *
     * @param ResponsiveCropInterface $crop
     * @param Phrase $failure Message when the crop cannot be deleted
     * @return void
     * @throws CouldNotSaveException When the crop cannot be deleted
     */
    private function delete(ResponsiveCropInterface $crop, Phrase $failure): void
    {
        try {
            $this->cropRepository->delete($crop);
        } catch (CouldNotDeleteException $exception) {
            throw new CouldNotSaveException($failure, $exception);
        }
    }

    /**
     * The stored crop, or a new one, set to the desired state and pointed at its new files
     *
     * @param CropChange $change
     * @param int $bannerId
     * @param CropWriteResult $result
     * @return ResponsiveCropInterface
     */
    private function updatedCrop(CropChange $change, int $bannerId, CropWriteResult $result): ResponsiveCropInterface
    {
        $input = $change->getInput();
        $crop = $change->getCurrent() ?? $this->cropFactory->create();
        $crop->setBannerId($bannerId);
        $crop->setBreakpointId($input->getBreakpointId());
        $crop->setSourceImage($input->getSourceImage());
        $crop->setCropRect($input->getRect());
        $crop->setIsEnabled($input->isEnabled());
        $result->applyTo($crop);

        return $crop;
    }
}
