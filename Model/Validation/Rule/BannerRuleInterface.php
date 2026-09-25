<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\Validation\Rule;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Magento\Framework\Phrase;

/**
 * One rule a banner must meet before it is saved; the banner validator runs every rule registered in its
 * `di.xml` pool.
 *
 * A rule reports what is wrong instead of throwing, so the validator can show the admin every broken rule at once.
 */
interface BannerRuleInterface
{
    /**
     * Messages describing how the banner breaks the rule; empty when it meets it
     *
     * @param BannerInterface $banner
     * @return list<Phrase>
     */
    public function validate(BannerInterface $banner): array;
}
