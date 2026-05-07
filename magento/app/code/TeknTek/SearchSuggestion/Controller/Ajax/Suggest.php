<?php
namespace TeknTek\SearchSuggestion\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

class Suggest extends Action
{
    private $resultJsonFactory;
    private $productCollectionFactory;
    private $imageHelper;
    private $priceHelper;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProductCollectionFactory $productCollectionFactory,
        ImageHelper $imageHelper,
        PriceHelper $priceHelper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->imageHelper = $imageHelper;
        $this->priceHelper = $priceHelper;
    }

    public function execute()
    {
        $q = trim((string)$this->getRequest()->getParam('q', ''));
        $result = $this->resultJsonFactory->create();

        if (strlen($q) < 1) {
            return $result->setData([]);
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'price', 'small_image']);
        $collection->addAttributeToFilter('visibility', ['neq' => 1]);
        $collection->addAttributeToFilter('status', ['eq' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED]);
        $collection->addAttributeToFilter('name', ['like' => '%' . $q . '%']);
        $collection->setPageSize(6);

        $items = [];
        foreach ($collection as $product) {
            try {
                $imageUrl = '';
                if ($product->getSmallImage() && $product->getSmallImage() !== 'no_selection') {
                    $imageUrl = $this->imageHelper->init($product, 'product_small_image')->resize(200)->getUrl();
                }

                $finalPrice = $product->getFinalPrice();
                $regularPrice = $product->getPrice();

                $items[] = [
                    'title' => $product->getName(),
                    'product_url' => $product->getProductUrl(),
                    'product_image' => $imageUrl,
                    'final_price' => $this->priceHelper->currency($finalPrice, true, false),
                    'regular_price' => $this->priceHelper->currency($regularPrice, true, false),
                    'final_price_amount' => (float)$finalPrice,
                    'regular_price_amount' => (float)$regularPrice,
                    'has_discount' => ($finalPrice < $regularPrice),
                ];
            } catch (\Throwable $e) {
                // skip product on error
            }
        }

        return $result->setData($items);
    }
}
