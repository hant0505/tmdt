<?php
namespace Vendor\Rma\Api;

use Vendor\Rma\Api\Data\RmaInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface RmaRepositoryInterface
{
    /**
     * Save RMA
     *
     * @param RmaInterface $rma
     * @return RmaInterface
     */
    public function save(RmaInterface $rma);

    /**
     * Get RMA by ID
     *
     * @param int $rmaId
     * @return RmaInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($rmaId);

    /**
     * Get list of RMA
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete RMA
     *
     * @param RmaInterface $rma
     * @return bool
     */
    public function delete(RmaInterface $rma);

    /**
     * Delete RMA by ID
     *
     * @param int $rmaId
     * @return bool
     */
    public function deleteById($rmaId);
}