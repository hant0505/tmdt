<?php

declare(strict_types=1);

namespace TeknTek\Homepage\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TeknTek\Homepage\Model\OfferPriceProvider;

class ApplyOfferQuoteItemPrice implements ObserverInterface
{
    public function __construct(
        private readonly OfferPriceProvider $offerPriceProvider
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quoteItem = $observer->getEvent()->getQuoteItem();
        if (!$quoteItem) {
            return;
        }

        $product = $quoteItem->getProduct();
        if (!$product || !(int) $product->getId()) {
            return;
        }

        $offerPrice = $this->offerPriceProvider->getDiscountedPriceForProduct($product);
        if ($offerPrice === null) {
            return;
        }

        $basePrice = (float) $product->getPrice();
        if ($basePrice <= 0 || $offerPrice >= $basePrice) {
            return;
        }

        $quoteItem->setCustomPrice($offerPrice);
        $quoteItem->setOriginalCustomPrice($offerPrice);
        $quoteItem->getProduct()->setIsSuperMode(true);
    }
}
