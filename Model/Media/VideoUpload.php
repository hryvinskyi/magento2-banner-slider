<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Media;

use Hryvinskyi\BannerSliderApi\Api\Config\VideoConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\VideoUploadInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\StoredMedia;
use Hryvinskyi\BannerSliderApi\Api\Value\UploadedFile;

/**
 * Stores banner videos under the package's `video` media root.
 *
 * The accepted types come from `di.xml`, one entry per MIME type a video provider can play, each with the extension a
 * stored file of that type gets. Several MIME types may share an extension: an M4V video is stored as `.mp4`, which
 * the MP4 provider plays. The admin uploader posts once and stores the returned path; an upload no banner ends up using
 * is left to the orphan media sweep.
 */
class VideoUpload implements VideoUploadInterface
{
    private const ROOT_PURPOSE = 'video';
    private const MIME_KEY = 'mime';
    private const EXTENSION_KEY = 'extension';

    /**
     * @var array<string,string> MIME type => extension
     */
    private readonly array $allowedTypes;

    /**
     * @param UploadedFileValidator $validator
     * @param VideoConfigInterface $videoConfig
     * @param UploadedFileStore $fileStore
     * @param MediaPaths $mediaPaths
     * @param array<string,array<string,string>> $allowedTypes Accepted videos by name, each with its `mime` type and
     *     the `extension` a stored file of that type gets
     * @throws \InvalidArgumentException When a MIME type or extension is empty, or a MIME type is listed twice
     */
    public function __construct(
        private readonly UploadedFileValidator $validator,
        private readonly VideoConfigInterface $videoConfig,
        private readonly UploadedFileStore $fileStore,
        private readonly MediaPaths $mediaPaths,
        array $allowedTypes = []
    ) {
        $types = [];
        foreach ($allowedTypes as $name => $type) {
            $mimeType = strtolower(trim($type[self::MIME_KEY] ?? ''));
            $extension = strtolower(trim($type[self::EXTENSION_KEY] ?? ''));
            if ($extension === '' || $mimeType === '' || isset($types[$mimeType])) {
                throw new \InvalidArgumentException(sprintf(
                    'Accepted video type "%s" needs a unique MIME type and an extension, got "%s" => "%s".',
                    $name,
                    $mimeType,
                    $extension
                ));
            }
            $types[$mimeType] = $extension;
        }
        $this->allowedTypes = $types;
    }

    /**
     * @inheritDoc
     */
    public function upload(UploadedFile $file): StoredMedia
    {
        $accepted = $this->validator->validate($file, $this->allowedTypes, $this->videoConfig->getMaxUploadBytes());

        return new StoredMedia(
            $this->fileStore->store($file, $this->mediaPaths->getRoot(self::ROOT_PURPOSE), $accepted['extension']),
            null,
            $accepted['mime'],
            $accepted['size']
        );
    }
}
