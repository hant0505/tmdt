<?php

declare(strict_types=1);

namespace TeknTek\CatalogTweaks\Plugin\Catalog;

use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Framework\App\RequestInterface;

class ToolbarLimitPlugin
{
    private const FIXED_LIMIT = 10;
    private const LIMIT_DATA_KEY = 'tekntek_dynamic_limit';

    public function __construct(
        private readonly RequestInterface $request
    ) {
    }

    public function aroundSetCollection(Toolbar $subject, callable $proceed, $collection)
    {
        if ($this->shouldApplyDynamicLimit()) {
            $subject->setData(self::LIMIT_DATA_KEY, self::FIXED_LIMIT);

            if (
                is_object($collection)
                && method_exists($collection, 'setPageSize')
                && method_exists($collection, 'setCurPage')
            ) {
                $collection->setCurPage(max(1, (int) $subject->getCurrentPage()));
                $collection->setPageSize(self::FIXED_LIMIT);
            }
        }

        return $proceed($collection);
    }

    public function aroundGetLimit(Toolbar $subject, callable $proceed)
    {
        $dynamicLimit = (int) $subject->getData(self::LIMIT_DATA_KEY);
        if ($dynamicLimit > 0 && $this->shouldApplyDynamicLimit()) {
            return $dynamicLimit;
        }

        return $proceed();
    }

    public function aroundGetAvailableLimit(Toolbar $subject, callable $proceed): array
    {
        $dynamicLimit = (int) $subject->getData(self::LIMIT_DATA_KEY);
        if ($dynamicLimit > 0 && $this->shouldApplyDynamicLimit()) {
            return [$dynamicLimit => $dynamicLimit];
        }

        return $proceed();
    }

    private function shouldApplyDynamicLimit(): bool
    {
        $fullActionName = (string) $this->request->getFullActionName();

        return in_array($fullActionName, ['catalog_category_view', 'catalogsearch_result_index'], true);
    }
}
