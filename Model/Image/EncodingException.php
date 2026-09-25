<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Image;

use Magento\Framework\Exception\LocalizedException;

/**
 * An image could not be processed: it cannot be decoded, is too large to decode, or cannot be encoded into the
 * requested format.
 *
 * The message is safe to show an admin: it never carries a server path or tool output. Those details travel in the
 * previous exception, for the log.
 */
class EncodingException extends LocalizedException
{
}
