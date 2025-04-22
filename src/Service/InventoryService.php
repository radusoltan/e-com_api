<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductInventory;
use App\Entity\ProductVariation;
use App\Entity\Warehouse;
use App\Repository\ProductInventoryRepository;
use App\Repository\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class InventoryService
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductInventoryRepository $inventoryRepository,
        private WarehouseRepository $warehouseRepository,
        private CacheService $cacheService
    ) {}

    /**
     * Get stock information for a product
     */
    public function getProductStock(Product $product): array
    {
        $cacheKey = 'product_stock_' . $product->getId();

        return $this->cacheService->get(
            $cacheKey,
            function() use ($product) {
                $totalQuantity = 0;
                $availableQuantity = 0;
                $reservedQuantity = 0;
                $warehouseData = [];

                foreach ($product->getInventories() as $inventory) {
                    $warehouseId = $inventory->getWarehouse()->getId();
                    $totalQuantity += $inventory->getQuantity();
                    $availableQuantity += $inventory->getAvailableQuantity();
                    $reservedQuantity += $inventory->getReserved();

                    $warehouseData[$warehouseId] = [
                        'warehouse' => $inventory->getWarehouse(),
                        'quantity' => $inventory->getQuantity(),
                        'available' => $inventory->getAvailableQuantity(),
                        'reserved' => $inventory->getReserved(),
                        'status' => $inventory->getStatus(),
                        'backorders_allowed' => $inventory->isBackordersAllowed(),
                    ];
                }

                return [
                    'total_quantity' => $totalQuantity,
                    'available_quantity' => $availableQuantity,
                    'reserved_quantity' => $reservedQuantity,
                    'warehouses' => $warehouseData,
                    'has_stock' => $availableQuantity > 0,
                    'backorders_allowed' => $this->isBackorderAllowed($product),
                    'status' => $this->getOverallStatus($product),
                ];
            },
            300, // 5 minute cache
            false,
            ['product', 'inventory', "product_{$product->getId()}"]
        );
    }

    /**
     * Get stock information for a product variation
     */
    public function getVariationStock(ProductVariation $variation): array
    {
        $cacheKey = 'variation_stock_' . $variation->getId();

        return $this->cacheService->get(
            $cacheKey,
            function() use ($variation) {
                $totalQuantity = 0;
                $availableQuantity = 0;
                $reservedQuantity = 0;
                $warehouseData = [];

                foreach ($variation->getInventories() as $inventory) {
                    $warehouseId = $inventory->getWarehouse()->getId();
                    $totalQuantity += $inventory->getQuantity();
                    $availableQuantity += $inventory->getAvailableQuantity();
                    $reservedQuantity += $inventory->getReserved();

                    $warehouseData[$warehouseId] = [
                        'warehouse' => $inventory->getWarehouse(),
                        'quantity' => $inventory->getQuantity(),
                        'available' => $inventory->getAvailableQuantity(),
                        'reserved' => $inventory->getReserved(),
                        'status' => $inventory->getStatus(),
                        'backorders_allowed' => $inventory->isBackordersAllowed(),
                    ];
                }

                return [
                    'total_quantity' => $totalQuantity,
                    'available_quantity' => $availableQuantity,
                    'reserved_quantity' => $reservedQuantity,
                    'warehouses' => $warehouseData,
                    'has_stock' => $availableQuantity > 0,
                    'backorders_allowed' => $this->isVariationBackorderAllowed($variation),
                    'status' => $this->getVariationOverallStatus($variation),
                ];
            },
            300, // 5 minute cache
            false,
            ['product', 'inventory', "variation_{$variation->getId()}"]
        );
    }

    /**
     * Update stock quantity for a product in a specific warehouse
     */
    public function updateProductStock(Product $product, Warehouse $warehouse, int $quantity): bool
    {
        $inventory = $this->getOrCreateInventory($product, $warehouse);
        $inventory->setQuantity($quantity);

        $this->entityManager->persist($inventory);
        $this->entityManager->flush();

        // Invalidate cache
        $this->invalidateProductStockCache($product);

        return true;
    }

    /**
     * Update stock quantity for a variation in a specific warehouse
     */
    public function updateVariationStock(ProductVariation $variation, Warehouse $warehouse, int $quantity): bool
    {
        $inventory = $this->getOrCreateVariationInventory($variation, $warehouse);
        $inventory->setQuantity($quantity);

        $this->entityManager->persist($inventory);
        $this->entityManager->flush();

        // Invalidate cache
        $this->invalidateVariationStockCache($variation);
        $this->invalidateProductStockCache($variation->getParent());

        return true;
    }

    /**
     * Reserve stock for a product
     */
    public function reserveProductStock(Product $product, int $quantity): bool
    {
        // Don't allow reserving stock for configurable products directly
        if ($product->isConfigurable()) {
            return false;
        }

        // Get warehouses sorted by priority
        $warehouses = $this->warehouseRepository->findBy(['active' => true], ['priority' => 'ASC']);
        $remainingQuantity = $quantity;

        foreach ($warehouses as $warehouse) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $inventory = $this->findInventory($product, $warehouse);
            if (!$inventory) {
                continue;
            }

            $availableQuantity = $inventory->getAvailableQuantity();
            $quantityToReserve = min($availableQuantity, $remainingQuantity);

            if ($quantityToReserve > 0 || ($inventory->isBackordersAllowed() && $remainingQuantity > 0)) {
                $success = $inventory->reserve($quantityToReserve > 0 ? $quantityToReserve : $remainingQuantity);
                if ($success) {
                    $this->entityManager->persist($inventory);
                    $remainingQuantity -= $quantityToReserve;
                }
            }
        }

        // If we couldn't reserve all requested quantity and backorders are not allowed
        if ($remainingQuantity > 0 && !$this->isBackorderAllowed($product)) {
            // Rollback any reservations we made
            return false;
        }

        $this->entityManager->flush();

        // Invalidate cache
        $this->invalidateProductStockCache($product);

        return true;
    }

    /**
     * Reserve stock for a variation
     */
    public function reserveVariationStock(ProductVariation $variation, int $quantity): bool
    {
        // Get warehouses sorted by priority
        $warehouses = $this->warehouseRepository->findBy(['active' => true], ['priority' => 'ASC']);
        $remainingQuantity = $quantity;

        foreach ($warehouses as $warehouse) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $inventory = $this->findVariationInventory($variation, $warehouse);
            if (!$inventory) {
                continue;
            }

            $availableQuantity = $inventory->getAvailableQuantity();
            $quantityToReserve = min($availableQuantity, $remainingQuantity);

            if ($quantityToReserve > 0 || ($inventory->isBackordersAllowed() && $remainingQuantity > 0)) {
                $success = $inventory->reserve($quantityToReserve > 0 ? $quantityToReserve : $remainingQuantity);
                if ($success) {
                    $this->entityManager->persist($inventory);
                    $remainingQuantity -= $quantityToReserve;
                }
            }
        }

        // If we couldn't reserve all requested quantity and backorders are not allowed
        if ($remainingQuantity > 0 && !$this->isVariationBackorderAllowed($variation)) {
            // Rollback any reservations we made
            return false;
        }

        $this->entityManager->flush();

        // Invalidate cache
        $this->invalidateVariationStockCache($variation);
        $this->invalidateProductStockCache($variation->getParent());

        return true;
    }

    /**
     * Check if a product is in stock and available for purchase
     */
    public function isProductInStock(Product $product): bool
    {
        // For configurable products, check if any variations are in stock
        if ($product->isConfigurable()) {
            foreach ($product->getVariations() as $variation) {
                if ($variation->isActive() && $this->isVariationInStock($variation)) {
                    return true;
                }
            }
            return false;
        }

        // For simple products, check stock directly
        $stockInfo = $this->getProductStock($product);
        return $stockInfo['has_stock'] || $stockInfo['backorders_allowed'];
    }

    /**
     * Check if a variation is in stock and available for purchase
     */
    public function isVariationInStock(ProductVariation $variation): bool
    {
        $stockInfo = $this->getVariationStock($variation);
        return $stockInfo['has_stock'] || $stockInfo['backorders_allowed'];
    }

    /**
     * Find inventory record for product in warehouse
     */
    private function findInventory(Product $product, Warehouse $warehouse): ?ProductInventory
    {
        return $this->inventoryRepository->findOneBy([
            'product' => $product,
            'productVariation' => null,
            'warehouse' => $warehouse,
        ]);
    }

    /**
     * Find inventory record for variation in warehouse
     */
    private function findVariationInventory(ProductVariation $variation, Warehouse $warehouse): ?ProductInventory
    {
        return $this->inventoryRepository->findOneBy([
            'product' => null,
            'productVariation' => $variation,
            'warehouse' => $warehouse,
        ]);
    }

    /**
     * Get or create inventory for product in warehouse
     */
    private function getOrCreateInventory(Product $product, Warehouse $warehouse): ProductInventory
    {
        $inventory = $this->findInventory($product, $warehouse);

        if (!$inventory) {
            $inventory = new ProductInventory();
            $inventory->setProduct($product);
            $inventory->setWarehouse($warehouse);
            $inventory->setQuantity(0);
            $inventory->setStatus(ProductInventory::STATUS_OUT_OF_STOCK);
        }

        return $inventory;
    }

    /**
     * Get or create inventory for variation in warehouse
     */
    private function getOrCreateVariationInventory(ProductVariation $variation, Warehouse $warehouse): ProductInventory
    {
        $inventory = $this->findVariationInventory($variation, $warehouse);

        if (!$inventory) {
            $inventory = new ProductInventory();
            $inventory->setProductVariation($variation);
            $inventory->setWarehouse($warehouse);
            $inventory->setQuantity(0);
            $inventory->setStatus(ProductInventory::STATUS_OUT_OF_STOCK);
        }

        return $inventory;
    }

    /**
     * Check if backorders are allowed for a product
     */
    private function isBackorderAllowed(Product $product): bool
    {
        foreach ($product->getInventories() as $inventory) {
            if ($inventory->isBackordersAllowed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if backorders are allowed for a variation
     */
    private function isVariationBackorderAllowed(ProductVariation $variation): bool
    {
        foreach ($variation->getInventories() as $inventory) {
            if ($inventory->isBackordersAllowed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get overall status for a product
     */
    private function getOverallStatus(Product $product): string
    {
        $totalQuantity = 0;
        $isBackorderAllowed = false;

        foreach ($product->getInventories() as $inventory) {
            $totalQuantity += $inventory->getAvailableQuantity();
            if ($inventory->isBackordersAllowed()) {
                $isBackorderAllowed = true;
            }
        }

        if ($totalQuantity > 0) {
            return ProductInventory::STATUS_IN_STOCK;
        } elseif ($isBackorderAllowed) {
            return ProductInventory::STATUS_BACKORDER;
        } else {
            return ProductInventory::STATUS_OUT_OF_STOCK;
        }
    }

    /**
     * Get overall status for a product variation
     */
    private function getVariationOverallStatus(ProductVariation $variation): string
    {
        $totalQuantity = 0;
        $isBackorderAllowed = false;

        foreach ($variation->getInventories() as $inventory) {
            $totalQuantity += $inventory->getAvailableQuantity();
            if ($inventory->isBackordersAllowed()) {
                $isBackorderAllowed = true;
            }
        }

        if ($totalQuantity > 0) {
            return ProductInventory::STATUS_IN_STOCK;
        } elseif ($isBackorderAllowed) {
            return ProductInventory::STATUS_BACKORDER;
        } else {
            return ProductInventory::STATUS_OUT_OF_STOCK;
        }
    }

    /**
     * Invalidate product stock cache
     */
    private function invalidateProductStockCache(Product $product): void
    {
        $this->cacheService->delete('product_stock_' . $product->getId());
        $this->cacheService->invalidateTag("product_{$product->getId()}");
        $this->cacheService->invalidateTag('inventory');
    }

    /**
     * Invalidate variation stock cache
     */
    private function invalidateVariationStockCache(ProductVariation $variation): void
    {
        $this->cacheService->delete('variation_stock_' . $variation->getId());
        $this->cacheService->invalidateTag("variation_{$variation->getId()}");
        $this->cacheService->invalidateTag('inventory');
    }

}