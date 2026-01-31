<?php
/**
 * Copyright (c) 2021. MageCloud.  All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model;

use Hryvinskyi\BannerSliderApi\Api\UploadImageInterface;
use Hryvinskyi\MediaUploader\Api\Command\UploadInterface;

class UploadImage implements UploadImageInterface
{
    /**
     * UploadImage constructor.
     *
     * @param UploadInterface $commandUpload
     */
    public function __construct(private readonly UploadInterface $commandUpload)
    {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function execute(string $imageId = 'image'): array
    {
        return $this->commandUpload->execute($imageId);
    }
}
