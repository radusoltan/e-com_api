<?php

namespace App\Controller\Admin;

use App\DTO\Response\ApiResponse;
use App\Entity\ProductInventory;
use App\Repository\ProductInventoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariationRepository;
use App\Repository\WarehouseRepository;
use App\Service\CacheService;
use App\Service\PermissionService;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/inventory', name: 'api_admin_inventory_')]
final class InventoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private ProductVariationRepository $variationRepository,
        private ProductInventoryRepository $inventoryRepository,
        private WarehouseRepository $warehouseRepository,
        private ProductService $productService,
        private CacheService $cacheService,
        private PermissionService $permissionService,
        private ValidatorInterface $validator,
    ) {}

    /**
     * Get inventory list with pagination
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('inventory_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to view inventory'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $warehouseId = $request->query->get('warehouse');
        $productId = $request->query->get('product');
        $variationId = $request->query->get('variation');
        $sku = $request->query->get('sku');
        $status = $request->query->get('status');
        $sortBy = $request->query->get('sort_by', 'id');
        $sortOrder = strtoupper($request->query->get('sort_order', 'ASC'));

        // Validate sort order
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'ASC';
        }

        // Build query
        $queryBuilder = $this->inventoryRepository->createQueryBuilder('i');

        // Add necessary joins
        $queryBuilder->leftJoin('i.product', 'p')
            ->leftJoin('i.productVariation', 'v')
            ->leftJoin('i.warehouse', 'w')
            ->addSelect('p', 'v', 'w');

        // Apply warehouse filter
        if ($warehouseId) {
            $queryBuilder
                ->andWhere('i.warehouse = :warehouseId')
                ->setParameter('warehouseId', $warehouseId);
        }

        // Apply product filter
        if ($productId) {
            $queryBuilder
                ->andWhere('i.product = :productId')
                ->setParameter('productId', $productId);
        }

        // Apply variation filter
        if ($variationId) {
            $queryBuilder
                ->andWhere('i.productVariation = :variationId')
                ->setParameter('variationId', $variationId);
        }

        // Apply SKU filter
        if ($sku) {
            $queryBuilder
                ->andWhere('p.sku LIKE :sku OR v.sku LIKE :sku')
                ->setParameter('sku', '%' . $sku . '%');
        }

        // Apply status filter
        if ($status) {
            $queryBuilder
                ->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        // Validate sort field to prevent SQL injection
        $allowedSortFields = [
            'id', 'quantity', 'reserved', 'status'
        ];

        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'id'; // Default to ID if invalid sort field
        }

        // Add sorting
        $queryBuilder->orderBy('i.' . $sortBy, $sortOrder);

        // Create paginator
        $adapter = new QueryAdapter($queryBuilder);
        $pagerfanta = Pagerfanta::createForCurrentPageWithMaxPerPage(
            $adapter,
            $page,
            $limit
        );

        $inventoryItems = [];
        foreach ($pagerfanta->getCurrentPageResults() as $inventory) {
            $inventoryItems[] = $this->formatInventoryData($inventory);
        }

        return new JsonResponse(
            ApiResponse::success([
                'inventory_items' => $inventoryItems,
                'pagination' => [
                    'current_page' => $pagerfanta->getCurrentPage(),
                    'per_page' => $pagerfanta->getMaxPerPage(),
                    'total_items' => $pagerfanta->getNbResults(),
                    'total_pages' => $pagerfanta->getNbPages(),
                    'has_previous_page' => $pagerfanta->hasPreviousPage(),
                    'has_next_page' => $pagerfanta->hasNextPage(),
                ]
            ])->toArray()
        );
    }

    /**
     * Get inventory details by ID
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        if (!$this->permissionService->hasPermission('inventory_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied')->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $inventory = $this->inventoryRepository->find($id);

        if (!$inventory) {
            return new JsonResponse(
                ApiResponse::error('Inventory not found')->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(
            ApiResponse::success([
                'id' => $inventory->getId(),
                'product' => $inventory->getProduct()?->getId(),
                'variation' => $inventory->getProductVariation()?->getId(),
                'warehouse' => $inventory->getWarehouse()?->getId(),
                'quantity' => $inventory->getQuantity(),
                'available' => $inventory->getAvailableQuantity(),
                'reserved' => $inventory->getReserved(),
                'status' => $inventory->getStatus(),
                'backorders_allowed' => $inventory->isBackordersAllowed(),
            ])->toArray()
        );
    }

    /**
     * Update inventory quantity
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('inventory_update')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to update inventory'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $inventory = $this->inventoryRepository->find($id);

        if (!$inventory) {
            return new JsonResponse(
                ApiResponse::error('Inventory not found', ['id' => 'Inventory with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        // Parse request data
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(
                ApiResponse::error('Invalid JSON data')->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Update inventory data
        if (isset($data['quantity'])) {
            $inventory->setQuantity($data['quantity']);
        }

        if (isset($data['reserved'])) {
            $inventory->setReserved($data['reserved']);
        }

        if (isset($data['status'])) {
            $inventory->setStatus($data['status']);
        }

        if (isset($data['backorders_allowed'])) {
            $inventory->setBackordersAllowed((bool)$data['backorders_allowed']);
        }

        if (isset($data['low_stock_threshold'])) {
            $inventory->setLowStockThreshold($data['low_stock_threshold']);
        }

        if (isset($data['shelf_location'])) {
            $inventory->setShelfLocation($data['shelf_location']);
        }

        if (isset($data['batch_number'])) {
            $inventory->setBatchNumber($data['batch_number']);
        }

        if (isset($data['expiry_date'])) {
            $inventory->setExpiryDate(
                $data['expiry_date'] ? new \DateTimeImmutable($data['expiry_date']) : null
            );
        }

        // Update timestamp
        $inventory->setUpdatedAt(new \DateTimeImmutable());

        // Validate inventory
        $errors = $this->validator->validate($inventory);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(
                ApiResponse::error('Validation failed', $errorMessages)->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Save inventory
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('inventory');
        if ($inventory->getProduct()) {
            $this->cacheService->delete('product_stock_info_' . $inventory->getProduct()->getId());
        }
        if ($inventory->getProductVariation()) {
            $this->cacheService->delete('variation_stock_info_' . $inventory->getProductVariation()->getId());
        }

        return new JsonResponse(
            ApiResponse::success(
                $this->formatInventoryData($inventory),
                'Inventory updated successfully'
            )->toArray()
        );
    }

    /**
     * Create inventory record
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('inventory_update')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to update inventory'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        // Parse request data
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(
                ApiResponse::error('Invalid JSON data')->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validate required fields
        if (!isset($data['warehouse_id']) || (!isset($data['product_id']) && !isset($data['variation_id']))) {
            return new JsonResponse(
                ApiResponse::error('Missing required fields', ['required' => 'warehouse_id and (product_id or variation_id) are required'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $warehouse = $this->warehouseRepository->find($data['warehouse_id']);
        if (!$warehouse) {
            return new JsonResponse(
                ApiResponse::error('Warehouse not found', ['warehouse_id' => 'Warehouse with this ID does not exist'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $product = null;
        $variation = null;

        if (isset($data['product_id'])) {
            $product = $this->productRepository->find($data['product_id']);
            if (!$product) {
                return new JsonResponse(
                    ApiResponse::error('Product not found', ['product_id' => 'Product with this ID does not exist'])->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Check if inventory record already exists
            $existingInventory = $this->inventoryRepository->findOneBy([
                'product' => $product,
                'productVariation' => null,
                'warehouse' => $warehouse,
            ]);

            if ($existingInventory) {
                return new JsonResponse(
                    ApiResponse::error('Inventory record already exists', ['duplicate' => 'Inventory record for this product and warehouse already exists'])->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        if (isset($data['variation_id'])) {
            $variation = $this->variationRepository->find($data['variation_id']);
            if (!$variation) {
                return new JsonResponse(
                    ApiResponse::error('Variation not found', ['variation_id' => 'Variation with this ID does not exist'])->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Check if inventory record already exists
            $existingInventory = $this->inventoryRepository->findOneBy([
                'product' => null,
                'productVariation' => $variation,
                'warehouse' => $warehouse,
            ]);

            if ($existingInventory) {
                return new JsonResponse(
                    ApiResponse::error('Inventory record already exists', ['duplicate' => 'Inventory record for this variation and warehouse already exists'])->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        // Create new inventory record
        $inventory = new ProductInventory();
        $inventory->setWarehouse($warehouse);

        if ($product) {
            $inventory->setProduct($product);
        }

        if ($variation) {
            $inventory->setProductVariation($variation);
        }

        // Set inventory data
        $inventory->setQuantity($data['quantity'] ?? 0);
        $inventory->setReserved($data['reserved'] ?? 0);
        $inventory->setStatus($data['status'] ?? ProductInventory::STATUS_IN_STOCK);
        $inventory->setBackordersAllowed(isset($data['backorders_allowed']) ? (bool)$data['backorders_allowed'] : false);
        $inventory->setLowStockThreshold($data['low_stock_threshold'] ?? null);
        $inventory->setShelfLocation($data['shelf_location'] ?? null);
        $inventory->setBatchNumber($data['batch_number'] ?? null);

        if (isset($data['expiry_date'])) {
            $inventory->setExpiryDate(new \DateTimeImmutable($data['expiry_date']));
        }

        // Validate inventory
        $errors = $this->validator->validate($inventory);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(
                ApiResponse::error('Validation failed', $errorMessages)->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Save inventory
        $this->entityManager->persist($inventory);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('inventory');
        if ($product) {
            $this->cacheService->delete('product_stock_info_' . $product->getId());
        }
        if ($variation) {
            $this->cacheService->delete('variation_stock_info_' . $variation->getId());
        }

        return new JsonResponse(
            ApiResponse::success(
                $this->formatInventoryData($inventory),
                'Inventory record created successfully'
            )->toArray(),
            Response::HTTP_CREATED
        );
    }

    /**
     * Delete inventory record
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('inventory_update')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to update inventory'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $inventory = $this->inventoryRepository->find($id);

        if (!$inventory) {
            return new JsonResponse(
                ApiResponse::error('Inventory not found', ['id' => 'Inventory with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        $productId = $inventory->getProduct() ? $inventory->getProduct()->getId() : null;
        $variationId = $inventory->getProductVariation() ? $inventory->getProductVariation()->getId() : null;

        // Perform the delete
        $this->entityManager->remove($inventory);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('inventory');
        if ($productId) {
            $this->cacheService->delete('product_stock_info_' . $productId);
        }
        if ($variationId) {
            $this->cacheService->delete('variation_stock_info_' . $variationId);
        }

        return new JsonResponse(
            ApiResponse::success(null, 'Inventory record deleted successfully')->toArray()
        );
    }

    /**
     * Format inventory data for API response
     */
    private function formatInventoryData(ProductInventory $inventory): array
    {
        $data = [
            'id' => $inventory->getId(),
            'warehouse' => [
                'id' => $inventory->getWarehouse()->getId(),
                'name' => $inventory->getWarehouse()->getName(),
                'code' => $inventory->getWarehouse()->getCode(),
            ],
            'quantity' => $inventory->getQuantity(),
            'reserved' => $inventory->getReserved(),
            'available_quantity' => $inventory->getAvailableQuantity(),
            'status' => $inventory->getStatus(),
            'backorders_allowed' => $inventory->isBackordersAllowed(),
            'low_stock_threshold' => $inventory->getLowStockThreshold(),
            'is_low_stock' => $inventory->isLowStock(),
            'shelf_location' => $inventory->getShelfLocation(),
            'batch_number' => $inventory->getBatchNumber(),
            'expiry_date' => $inventory->getExpiryDate() ?
                $inventory->getExpiryDate()->format(\DateTimeInterface::ATOM) : null,
            'created_at' => $inventory->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $inventory->getUpdatedAt() ?
                $inventory->getUpdatedAt()->format(\DateTimeInterface::ATOM) : null,
        ];

        // Add product information if this is a product inventory
        if ($inventory->getProduct()) {
            $product = $inventory->getProduct();
            $data['product'] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'type' => $product->getType(),
            ];
            $data['product_id'] = $product->getId();
            $data['variation_id'] = null;
        }

        // Add variation information if this is a variation inventory
        if ($inventory->getProductVariation()) {
            $variation = $inventory->getProductVariation();
            $product = $variation->getParent();
            $data['variation'] = [
                'id' => $variation->getId(),
                'sku' => $variation->getSku(),
                'parent_id' => $product->getId(),
                'parent_name' => $product->getName(),
            ];
            $data['product_id'] = null;
            $data['variation_id'] = $variation->getId();
        }

        return $data;
    }
}