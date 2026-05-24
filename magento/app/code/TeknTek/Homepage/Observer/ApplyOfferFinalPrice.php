<?php

declare(strict_types=1);

namespace TeknTek\Homepage\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TeknTek\Homepage\Model\OfferPriceProvider;

class ApplyOfferFinalPrice implements ObserverInterface
{
    public function __construct(
        private readonly OfferPriceProvider $offerPriceProvider
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product || !(int) $product->getId()) {
            return;
        }

        $offerPrice = $this->offerPriceProvider->getDiscountedPriceForProduct($product);
        if ($offerPrice === null) {
            return;
        }

        $basePrice = (float) $product->getPrice();
        if ($basePrice > 0 && $offerPrice < $basePrice) {
            $product->setSpecialPrice($offerPrice);
            $product->setData('special_price', $offerPrice);
            $product->setFinalPrice($offerPrice);
        }
    }
}
