<?php
namespace Vendor\Rma\Model;

use Vendor\Rma\Api\Data\RmaInterface;
use Vendor\Rma\Api\RmaRepositoryInterface;
use Vendor\Rma\Model\ResourceModel\Rma as RmaResource;
use Vendor\Rma\Model\ResourceModel\Rma\CollectionFactory as RmaCollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotDeleteException;

class RmaRepository implements RmaRepositoryInterface
{
    public function __construct(
        private RmaResource $resource,
        private RmaFactory $rmaFactory,
        private RmaCollectionFactory $rmaCollectionFactory,
        private SearchResultsInterfaceFactory $searchResultsFactory
    ) {}

    public function save(RmaInterface $rma)
    {
        try {
            $this->resource->save($rma);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
        return $rma;
    }

    public function getById($rmaId)
    {
        $rma = $this->rmaFactory->create();
        $this->resource->load($rma, $rmaId);
        if (!$rma->getId()) {
            throw new NoSuchEntityException(__('RMA with id "%1" does not exist.', $rmaId));
        }
        return $rma;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->rmaCollectionFactory->create();
        $collection->addFilterToMap('rma_id', 'main_table.rma_id');

        /** @var SearchResults $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    public function delete(RmaInterface $rma)
    {
        try {
            $this->resource->delete($rma);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__($e->getMessage()));
        }
        return true;
    }

    public function deleteById($rmaId)
    {
        return $this->delete($this->getById($rmaId));
    }
}