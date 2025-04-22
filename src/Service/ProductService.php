<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductVariation;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;
use Symfony\Component\Uid\Uuid;

class ProductService
{
    private string $defaultCurrencyCode;
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private CacheService $cacheService,
        private CurrencyService $currencyService,
        string $defaultCurrencyCode = 'USD'
    ) {
        $this->defaultCurrencyCode = $defaultCurrencyCode;
    }

    /**
     * Find a product by ID with optional caching
     */
    public function findById(int $id, bool $useCache = true): ?Product
    {
        if (!$useCache) {
            return $this->productRepository->find($id);
        }

        $cacheKey = 'product_' . $id;

        return $this->cacheService->get(
            $cacheKey,
            fn() => $this->productRepository->find($id),
            3600 // 1 hour cache
        );
    }

    /**
     * Find a product by SKU with optional caching
     */
    public function findBySku(string $sku, bool $useCache = true): ?Product
    {
        if (!$useCache) {
            return $this->productRepository->findOneBy(['sku' => $sku]);
        }

        $cacheKey = 'product_sku_' . $sku;

        return $this->cacheService->get(
            $cacheKey,
            fn() => $this->productRepository->findOneBy(['sku' => $sku]),
            3600 // 1 hour cache
        );
    }

    /**
     * Get product price as Money object with respect to currency
     */
    public function getProductPrice(Product $product, ?string $currencyCode = null): Money
    {
        $currencyCode = $currencyCode ?? $product->getCurrencyCode() ?? $this->defaultCurrencyCode;
        $price = $product->getCurrentPrice();

        // If product currency matches requested currency, return directly
        if ($product->getCurrencyCode() === $currencyCode) {
            return new Money($price, new Currency($currencyCode));
        }

        // Otherwise convert the price
        return $this->currencyService->convert(
            new Money($price, new Currency($product->getCurrencyCode() ?? $this->defaultCurrencyCode)),
            new Currency($currencyCode)
        );
    }

    /**
     * Get variation price as Money object
     */
    public function getVariationPrice(ProductVariation $variation, ?string $currencyCode = null): Money
    {
        $currencyCode = $currencyCode ?? $variation->getParent()->getCurrencyCode() ?? $this->defaultCurrencyCode;
        $price = $variation->getEffectivePrice();

        // If product currency matches requested currency, return directly
        $productCurrencyCode = $variation->getParent()->getCurrencyCode() ?? $this->defaultCurrencyCode;
        if ($productCurrencyCode === $currencyCode) {
            return new Money($price, new Currency($currencyCode));
        }

        // Otherwise convert the price
        return $this->currencyService->convert(
            new Money($price, new Currency($productCurrencyCode)),
            new Currency($currencyCode)
        );
    }

    /**
     * Get formatted price with currency symbol
     */
    public function getFormattedPrice(Product|ProductVariation $item, ?string $currencyCode = null): string
    {
        if ($item instanceof Product) {
            $money = $this->getProductPrice($item, $currencyCode);
        } else {
            $money = $this->getVariationPrice($item, $currencyCode);
        }

        return $this->currencyService->format($money);
    }

    /**
     * Check if a product is available for purchase
     */
    public function isAvailableForPurchase(Product $product): bool
    {
        // Check if product is active
        if (!$product->isActive()) {
            return false;
        }

        // Check date range availability
        if (!$product->isAvailable()) {
            return false;
        }

        // For simple products, check inventory
        if ($product->isSimple()) {
            // Delegate to inventory service if needed
            return $this->hasAvailableInventory($product);
        }

        // For configurable products, check if there are active variations with inventory
        if ($product->isConfigurable()) {
            foreach ($product->getVariations() as $variation) {
                if ($variation->isActive() && $this->hasVariationInventory($variation)) {
                    return true;
                }
            }
            return false;
        }

        // For virtual/downloadable products, they're always available if active
        if ($product->isVirtual() || $product->isDownloadable()) {
            return true;
        }

        return false;
    }

    /**
     * Check if a product has available inventory
     */
    private function hasAvailableInventory(Product $product): bool
    {
        // Simple check - in real implementation, this would query the inventory service
        foreach ($product->getInventories() as $inventory) {
            if ($inventory->isAvailable() && $inventory->getAvailableQuantity() > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a variation has available inventory
     */
    private function hasVariationInventory(ProductVariation $variation): bool
    {
        // Simple check - in real implementation, this would query the inventory service
        foreach ($variation->getInventories() as $inventory) {
            if ($inventory->isAvailable() && $inventory->getAvailableQuantity() > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate a unique SKU
     */
    public function generateUniqueSku(string $prefix = ''): string
    {
        $uuid = Uuid::v4();
        $sku = $prefix . strtoupper(substr($uuid->toBase32(), 0, 8));

        // Ensure uniqueness
        while ($this->productRepository->findOneBy(['sku' => $sku])) {
            $uuid = Uuid::v4();
            $sku = $prefix . strtoupper(substr($uuid->toBase32(), 0, 8));
        }

        return $sku;
    }

    /**
     * Clear product cache
     */
    public function clearProductCache(Product $product): void
    {
        $this->cacheService->delete('product_' . $product->getId());
        $this->cacheService->delete('product_sku_' . $product->getSku());
    }

    /**
     * Get stock information for a product
     */
    public function getStockInfo(Product $product): array
    {
        $cacheKey = 'product_stock_info_' . $product->getId();

        return $this->cacheService->get(
            $cacheKey,
            function() use ($product) {
                // For simple products, check inventory directly
                if ($product->isSimple()) {
                    $totalQuantity = 0;
                    $availableQuantity = 0;
                    $backordersAllowed = false;
                    $status = 'out_of_stock';

                    // Aggregate inventory data from all warehouses
                    foreach ($product->getInventories() as $inventory) {
                        $totalQuantity += $inventory->getQuantity();
                        $availableQuantity += $inventory->getAvailableQuantity();

                        if ($inventory->isBackordersAllowed()) {
                            $backordersAllowed = true;
                        }

                        // Use the most optimistic status across warehouses
                        if ($inventory->getStatus() === 'in_stock' && $inventory->getAvailableQuantity() > 0) {
                            $status = 'in_stock';
                        } elseif ($inventory->getStatus() === 'backorder' && $status !== 'in_stock') {
                            $status = 'backorder';
                        }
                    }

                    return [
                        'total_quantity' => $totalQuantity,
                        'available_quantity' => $availableQuantity,
                        'has_stock' => $availableQuantity > 0,
                        'backorders_allowed' => $backordersAllowed,
                        'status' => $status,
                    ];
                }

                // For configurable products, check if any variations have stock
                if ($product->isConfigurable()) {
                    $hasStock = false;
                    $backordersAllowed = false;
                    $status = 'out_of_stock';

                    foreach ($product->getVariations() as $variation) {
                        if (!$variation->isActive()) {
                            continue;
                        }

                        // Get variation stock info
                        $variationStockInfo = $this->getVariationStockInfo($variation);

                        if ($variationStockInfo['has_stock']) {
                            $hasStock = true;
                        }

                        if ($variationStockInfo['backorders_allowed']) {
                            $backordersAllowed = true;
                        }

                        // Use the most optimistic status across variations
                        if ($variationStockInfo['status'] === 'in_stock') {
                            $status = 'in_stock';
                        } elseif ($variationStockInfo['status'] === 'backorder' && $status !== 'in_stock') {
                            $status = 'backorder';
                        }
                    }

                    return [
                        'has_stock' => $hasStock,
                        'backorders_allowed' => $backordersAllowed,
                        'status' => $status,
                    ];
                }

                // For virtual/downloadable products, they're always in stock
                if ($product->isVirtual() || $product->isDownloadable()) {
                    return [
                        'has_stock' => true,
                        'backorders_allowed' => true,
                        'status' => 'in_stock',
                    ];
                }

                // Default for other product types
                return [
                    'has_stock' => false,
                    'backorders_allowed' => false,
                    'status' => 'out_of_stock',
                ];
            },
            300 // 5 minute cache
        );
    }

    /**
     * Get stock information for a product variation
     */
    public function getVariationStockInfo(ProductVariation $variation): array
    {
        $totalQuantity = 0;
        $availableQuantity = 0;
        $backordersAllowed = false;
        $status = 'out_of_stock';

        // Aggregate inventory data from all warehouses
        foreach ($variation->getInventories() as $inventory) {
            $totalQuantity += $inventory->getQuantity();
            $availableQuantity += $inventory->getAvailableQuantity();

            if ($inventory->isBackordersAllowed()) {
                $backordersAllowed = true;
            }

            // Use the most optimistic status across warehouses
            if ($inventory->getStatus() === 'in_stock' && $inventory->getAvailableQuantity() > 0) {
                $status = 'in_stock';
            } elseif ($inventory->getStatus() === 'backorder' && $status !== 'in_stock') {
                $status = 'backorder';
            }
        }

        return [
            'total_quantity' => $totalQuantity,
            'available_quantity' => $availableQuantity,
            'has_stock' => $availableQuantity > 0,
            'backorders_allowed' => $backordersAllowed,
            'status' => $status,
        ];
    }

}