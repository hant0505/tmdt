<?php

declare(strict_types=1);

namespace TeknTek\CatalogTweaks\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;

class CheckoutData implements SectionSourceInterface
{
    public function getSectionData(): array
    {
        return [];
    }
}
