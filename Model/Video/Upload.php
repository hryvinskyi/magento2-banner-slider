<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Video;

use Hryvinskyi\BannerSliderApi\Api\Video\UploadInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\VideoPathConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Video upload service
 */
class Upload implements UploadInterface
{
    private ?WriteInterface $mediaDirectory = null;

    /**
     * @param Filesystem $filesystem
     * @param UploaderFactory $uploaderFactory
     * @param StoreManagerInterface $storeManager
     * @param VideoPathConfigInterface $videoPathConfig
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly UploaderFactory $uploaderFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly VideoPathConfigInterface $videoPathConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function uploadToTmp(string $fileId): array
    {
        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions($this->getAllowedExtensions());
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        $mediaDirectory = $this->getMediaDirectory();
        $tmpPath = $this->videoPathConfig->getTmpPath();
        $absoluteTmpPath = $mediaDirectory->getAbsolutePath($tmpPath);

        $result = $uploader->save($absoluteTmpPath);

        if (!$result) {
            throw new LocalizedException(__('Video upload failed.'));
        }

        $fileSize = $result['size'] ?? 0;
        if ($fileSize > $this->getMaxFileSize()) {
            $mediaDirectory->delete($tmpPath . '/' . $result['file']);
            throw new LocalizedException(
                __('The video file exceeds the maximum allowed size of %1 MB.', $this->getMaxFileSize() / 1048576)
            );
        }

        $result['path'] = $tmpPath;
        $result['url'] = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
            . $tmpPath . '/' . $result['file'];

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function moveFromTmp(string $fileName): string
    {
        $mediaDirectory = $this->getMediaDirectory();
        $tmpPath = $this->videoPathConfig->getTmpPath() . '/' . $fileName;
        $basePath = $this->videoPathConfig->getBasePath();
        $destinationPath = $basePath . '/' . $fileName;

        if (!$mediaDirectory->isFile($tmpPath)) {
            throw new LocalizedException(__('Temporary video file not found.'));
        }

        if ($mediaDirectory->isFile($destinationPath)) {
            $pathInfo = pathinfo($fileName);
            $fileName = $pathInfo['filename'] . '_' . time() . '.' . ($pathInfo['extension'] ?? 'mp4');
            $destinationPath = $basePath . '/' . $fileName;
        }

        $mediaDirectory->renameFile($tmpPath, $destinationPath);

        return $fileName;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->videoPathConfig->getAllowedMimeTypes();
    }

    /**
     * @inheritDoc
     */
    public function getAllowedExtensions(): array
    {
        return $this->videoPathConfig->getAllowedExtensions();
    }

    /**
     * @inheritDoc
     */
    public function getMaxFileSize(): int
    {
        return $this->videoPathConfig->getMaxFileSize();
    }

    /**
     * Get media directory write interface
     *
     * @return WriteInterface
     */
    private function getMediaDirectory(): WriteInterface
    {
        if ($this->mediaDirectory === null) {
            $this->mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        }

        return $this->mediaDirectory;
    }
}
