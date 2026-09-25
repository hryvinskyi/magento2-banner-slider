<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSlider\Model\SearchResults;

use Magento\Framework\Api\SearchCriteriaFactory;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * The search criteria and total count every page of search results carries; subclasses hold their typed items.
 *
 * A page that was never given its criteria reports empty criteria rather than null.
 */
abstract class AbstractSearchResults
{
    /**
     * @var SearchCriteriaInterface|null
     */
    private ?SearchCriteriaInterface $searchCriteria = null;

    /**
     * @var int
     */
    private int $totalCount = 0;

    /**
     * @param SearchCriteriaFactory $searchCriteriaFactory
     */
    public function __construct(
        private readonly SearchCriteriaFactory $searchCriteriaFactory
    ) {
    }

    /**
     * The criteria the page was searched with
     *
     * @return SearchCriteriaInterface
     */
    public function getSearchCriteria(): SearchCriteriaInterface
    {
        if ($this->searchCriteria === null) {
            $this->searchCriteria = $this->searchCriteriaFactory->create();
        }

        return $this->searchCriteria;
    }

    /**
     * Set the criteria the page was searched with
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return $this
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria): static
    {
        $this->searchCriteria = $searchCriteria;

        return $this;
    }

    /**
     * Number of matches over all pages
     *
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * Set the number of matches over all pages
     *
     * @param int $totalCount
     * @return $this
     */
    public function setTotalCount($totalCount): static
    {
        $this->totalCount = max(0, (int)$totalCount);

        return $this;
    }
}
