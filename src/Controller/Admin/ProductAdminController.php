<?php

namespace App\Controller\Admin;

use App\DTO\Response\ApiResponse;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\CacheService;
use App\Service\ProductService;
use App\Service\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/products', name: 'api_admin_products_')]
final class ProductAdminController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private ProductService $productService,
        private CacheService $cacheService,
        private PermissionService $permissionService,
        private ValidatorInterface $validator,
    ) {}

    /**
     * Get list of products with pagination (admin view)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to view products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $categoryId = $request->query->get('category');
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sort_by', 'id');
        $sortOrder = strtoupper($request->query->get('sort_order', 'ASC'));
        $activeFilter = $request->query->get('active');
        $typeFilter = $request->query->get('type');

        // Validate sort order
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'ASC';
        }

        // Build query
        $queryBuilder = $this->productRepository->createQueryBuilder('p');

        // Apply search filter
        if (!empty($search)) {
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->like('p.name', ':search'),
                        $queryBuilder->expr()->like('p.sku', ':search'),
                        $queryBuilder->expr()->like('p.description', ':search')
                    )
                )
                ->setParameter('search', '%' . $search . '%');
        }

        // Apply category filter
        if ($categoryId) {
            $queryBuilder
                ->innerJoin('p.categories', 'c')
                ->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        // Apply active filter
        if ($activeFilter !== null) {
            $isActive = filter_var($activeFilter, FILTER_VALIDATE_BOOLEAN);
            $queryBuilder
                ->andWhere('p.active = :active')
                ->setParameter('active', $isActive);
        }

        // Apply type filter
        if ($typeFilter) {
            $queryBuilder
                ->andWhere('p.type = :type')
                ->setParameter('type', $typeFilter);
        }

        // Validate sort field to prevent SQL injection
        $allowedSortFields = [
            'id', 'name', 'price', 'createdAt', 'updatedAt', 'sku', 'type', 'active'
        ];

        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'id'; // Default to ID if invalid sort field
        }

        // Add sorting
        $queryBuilder->orderBy('p.' . $sortBy, $sortOrder);

        // Create paginator
        $adapter = new QueryAdapter($queryBuilder);
        $pagerfanta = Pagerfanta::createForCurrentPageWithMaxPerPage(
            $adapter,
            $page,
            $limit
        );

        $products = [];
        foreach ($pagerfanta->getCurrentPageResults() as $product) {
            $products[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'type' => $product->getType(),
                'price' => $product->getPrice(),
                'formatted_price' => $this->productService->getFormattedPrice($product),
                'active' => $product->isActive(),
                'created_at' => $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updated_at' => $product->getUpdatedAt() ? $product->getUpdatedAt()->format(\DateTimeInterface::ATOM) : null,
                'categories' => $product->getCategories()->map(fn($category) => [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                ])->toArray(),
                'has_stock' => $product->getTotalAvailableQuantity() > 0,
                'stock_qty' => $product->getTotalAvailableQuantity(),
                'variations_count' => $product->isConfigurable() ? $product->getVariations()->count() : 0,
            ];
        }

        return new JsonResponse(
            ApiResponse::success([
                'products' => $products,
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
     * Get product details by ID (admin view)
     */
    #[Route('/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getById(int $id): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to view products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $product = $this->productRepository->find($id);

        if (!$product) {
            return new JsonResponse(
                ApiResponse::error('Product not found', ['id' => 'Product with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(
            ApiResponse::success(
                $this->formatProductDataForAdmin($product)
            )->toArray()
        );
    }

    /**
     * Create a new product
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_create')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to create products'])->toArray(),
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

        // Create new product
        $product = new Product();

        // Set basic product data
        $product->setName($data['name'] ?? '');
        $product->setSku($data['sku'] ?? $this->productService->generateUniqueSku());
        $product->setType($data['type'] ?? Product::TYPE_SIMPLE);
        $product->setPrice($data['price'] ?? 0);
        $product->setCurrencyCode($data['currency_code'] ?? 'USD');
        $product->setWeight($data['weight'] ?? null);
        $product->setShortDescription($data['short_description'] ?? null);
        $product->setDescription($data['description'] ?? null);
        $product->setActive($data['active'] ?? false);
        $product->setFeatured($data['featured'] ?? false);

        // Set special price if provided
        if (isset($data['special_price'])) {
            $product->setSpecialPrice($data['special_price']);

            if (isset($data['special_price_from'])) {
                $product->setSpecialPriceFrom(new \DateTimeImmutable($data['special_price_from']));
            }

            if (isset($data['special_price_to'])) {
                $product->setSpecialPriceTo(new \DateTimeImmutable($data['special_price_to']));
            }
        }

        // Set availability dates if provided
        if (isset($data['available_from'])) {
            $product->setAvailableFrom(new \DateTimeImmutable($data['available_from']));
        }

        if (isset($data['available_to'])) {
            $product->setAvailableTo(new \DateTimeImmutable($data['available_to']));
        }

        // Add categories if provided
        if (isset($data['category_ids']) && is_array($data['category_ids'])) {
            foreach ($data['category_ids'] as $categoryId) {
                $category = $categoryRepository->find($categoryId);
                if ($category) {
                    $product->addCategory($category);
                }
            }
        }

        // Validate product
        $errors = $this->validator->validate($product);
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

        // Save product
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('products');

        return new JsonResponse(
            ApiResponse::success(
                $this->formatProductDataForAdmin($product),
                'Product created successfully'
            )->toArray(),
            Response::HTTP_CREATED
        );
    }

    /**
     * Update an existing product
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to edit products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $product = $this->productRepository->find($id);

        if (!$product) {
            return new JsonResponse(
                ApiResponse::error('Product not found', ['id' => 'Product with this ID does not exist'])->toArray(),
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

        // Update product data
        if (isset($data['name'])) {
            $product->setName($data['name']);
        }

        if (isset($data['sku'])) {
            // Check if SKU is already used by another product
            $existingProduct = $this->productRepository->findOneBy(['sku' => $data['sku']]);
            if ($existingProduct && $existingProduct->getId() !== $product->getId()) {
                return new JsonResponse(
                    ApiResponse::error('Validation failed', ['sku' => 'This SKU is already in use'])->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $product->setSku($data['sku']);
        }

        if (isset($data['price'])) {
            $product->setPrice($data['price']);
        }

        if (isset($data['currency_code'])) {
            $product->setCurrencyCode($data['currency_code']);
        }

        if (isset($data['weight'])) {
            $product->setWeight($data['weight']);
        }

        if (isset($data['short_description'])) {
            $product->setShortDescription($data['short_description']);
        }

        if (isset($data['description'])) {
            $product->setDescription($data['description']);
        }

        if (isset($data['active'])) {
            $product->setActive((bool)$data['active']);
        }

        if (isset($data['featured'])) {
            $product->setFeatured((bool)$data['featured']);
        }

        // Update special price fields
        if (isset($data['special_price'])) {
            $product->setSpecialPrice($data['special_price']);
        }

        if (isset($data['special_price_from'])) {
            $product->setSpecialPriceFrom(
                $data['special_price_from'] ? new \DateTimeImmutable($data['special_price_from']) : null
            );
        }

        if (isset($data['special_price_to'])) {
            $product->setSpecialPriceTo(
                $data['special_price_to'] ? new \DateTimeImmutable($data['special_price_to']) : null
            );
        }

        // Update availability dates
        if (isset($data['available_from'])) {
            $product->setAvailableFrom(
                $data['available_from'] ? new \DateTimeImmutable($data['available_from']) : null
            );
        }

        if (isset($data['available_to'])) {
            $product->setAvailableTo(
                $data['available_to'] ? new \DateTimeImmutable($data['available_to']) : null
            );
        }

        // Update categories if provided
        if (isset($data['category_ids']) && is_array($data['category_ids'])) {
            // Remove all existing categories
            foreach ($product->getCategories()->toArray() as $category) {
                $product->removeCategory($category);
            }

            // Add new categories
            foreach ($data['category_ids'] as $categoryId) {
                $category = $categoryRepository->find($categoryId);
                if ($category) {
                    $product->addCategory($category);
                }
            }
        }

        // Validate product
        $errors = $this->validator->validate($product);
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

        // Update timestamp
        $product->setUpdatedAt(new \DateTimeImmutable());

        // Save product
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('products');
        $this->cacheService->delete('product_detail_' . $product->getId());
        $this->cacheService->delete('product_sku_' . $product->getSku());

        return new JsonResponse(
            ApiResponse::success(
                $this->formatProductDataForAdmin($product),
                'Product updated successfully'
            )->toArray()
        );
    }

    /**
     * Delete a product
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_delete')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to delete products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $product = $this->productRepository->find($id);

        if (!$product) {
            return new JsonResponse(
                ApiResponse::error('Product not found', ['id' => 'Product with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        // Perform the delete
        $this->entityManager->remove($product);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('products');
        $this->cacheService->delete('product_detail_' . $id);
        $this->cacheService->delete('product_sku_' . $product->getSku());

        return new JsonResponse(
            ApiResponse::success(null, 'Product deleted successfully')->toArray()
        );
    }

    /**
     * Batch update product status (active/inactive)
     */
    #[Route('/batch/status', name: 'batch_status', methods: ['POST'])]
    public function batchUpdateStatus(Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to edit products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        // Parse request data
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['product_ids']) || !isset($data['active'])) {
            return new JsonResponse(
                ApiResponse::error('Invalid request data', ['data' => 'Missing required fields: product_ids, active'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $productIds = $data['product_ids'];
        $active = (bool)$data['active'];

        if (!is_array($productIds) || empty($productIds)) {
            return new JsonResponse(
                ApiResponse::error('Invalid product IDs', ['product_ids' => 'Product IDs must be a non-empty array'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Find the products to update
        $products = $this->productRepository->findBy(['id' => $productIds]);

        if (empty($products)) {
            return new JsonResponse(
                ApiResponse::error('No products found', ['product_ids' => 'No products found with the provided IDs'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        // Update the status for each product
        $updatedCount = 0;
        foreach ($products as $product) {
            if ($product->isActive() !== $active) {
                $product->setActive($active);
                $product->setUpdatedAt(new \DateTimeImmutable());
                $updatedCount++;

                // Invalidate individual product cache
                $this->cacheService->delete('product_detail_' . $product->getId());
                $this->cacheService->delete('product_sku_' . $product->getSku());
            }
        }

        $this->entityManager->flush();

        // Invalidate general product cache
        $this->cacheService->invalidateTag('products');

        return new JsonResponse(
            ApiResponse::success(
                ['updated_count' => $updatedCount],
                sprintf('%d products updated successfully', $updatedCount)
            )->toArray()
        );
    }

    /**
     * Batch delete products
     */
    #[Route('/batch/delete', name: 'batch_delete', methods: ['POST'])]
    public function batchDelete(Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_delete')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to delete products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        // Parse request data
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['product_ids'])) {
            return new JsonResponse(
                ApiResponse::error('Invalid request data', ['data' => 'Missing required field: product_ids'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $productIds = $data['product_ids'];

        if (!is_array($productIds) || empty($productIds)) {
            return new JsonResponse(
                ApiResponse::error('Invalid product IDs', ['product_ids' => 'Product IDs must be a non-empty array'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Find the products to delete
        $products = $this->productRepository->findBy(['id' => $productIds]);

        if (empty($products)) {
            return new JsonResponse(
                ApiResponse::error('No products found', ['product_ids' => 'No products found with the provided IDs'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        // Delete each product
        $deletedCount = 0;
        foreach ($products as $product) {
            $this->entityManager->remove($product);
            $deletedCount++;

            // Invalidate individual product cache
            $this->cacheService->delete('product_detail_' . $product->getId());
            $this->cacheService->delete('product_sku_' . $product->getSku());
        }

        $this->entityManager->flush();

        // Invalidate general product cache
        $this->cacheService->invalidateTag('products');

        return new JsonResponse(
            ApiResponse::success(
                ['deleted_count' => $deletedCount],
                sprintf('%d products deleted successfully', $deletedCount)
            )->toArray()
        );
    }

    /**
     * Format product data for admin API response
     */
    private function formatProductDataForAdmin(Product $product): array
    {
        $productData = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'type' => $product->getType(),
            'price' => $product->getPrice(),
            'formatted_price' => $this->productService->getFormattedPrice($product),
            'special_price' => $product->getSpecialPrice(),
            'has_special_price' => $product->hasValidSpecialPrice(),
            'special_price_from' => $product->getSpecialPriceFrom() ?
                $product->getSpecialPriceFrom()->format(\DateTimeInterface::ATOM) : null,
            'special_price_to' => $product->getSpecialPriceTo() ?
                $product->getSpecialPriceTo()->format(\DateTimeInterface::ATOM) : null,
            'currency_code' => $product->getCurrencyCode() ?? 'USD',
            'short_description' => $product->getShortDescription(),
            'description' => $product->getDescription(),
            'weight' => $product->getWeight(),
            'active' => $product->isActive(),
            'featured' => $product->isFeatured(),
            'available_from' => $product->getAvailableFrom() ?
                $product->getAvailableFrom()->format(\DateTimeInterface::ATOM) : null,
            'available_to' => $product->getAvailableTo() ?
                $product->getAvailableTo()->format(\DateTimeInterface::ATOM) : null,
            'created_at' => $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $product->getUpdatedAt() ?
                $product->getUpdatedAt()->format(\DateTimeInterface::ATOM) : null,
        ];

        // Add stock information
        $productData['stock_info'] = [
            'total_quantity' => $product->getTotalStockQuantity(),
            'available_quantity' => $product->getTotalAvailableQuantity(),
            'has_stock' => $product->hasStock(),
        ];

        // Add categories
        $categories = [];
        foreach ($product->getCategories() as $category) {
            $categories[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ];
        }
        $productData['categories'] = $categories;

        // Add images
        $images = [];
        foreach ($product->getImages() as $image) {
            $images[] = [
                'id' => $image->getId(),
                'path' => $image->getPath(),
                'alt' => $image->getAlt(),
                'title' => $image->getTitle(),
                'is_default' => $image->isDefault(),
                'position' => $image->getPosition(),
            ];
        }
        $productData['images'] = $images;

        // Add attributes
        $attributes = [];
        foreach ($product->getAttributeValues() as $attributeValue) {
            $attributes[] = [
                'id' => $attributeValue->getId(),
                'attribute_id' => $attributeValue->getAttribute()->getId(),
                'code' => $attributeValue->getAttribute()->getCode(),
                'name' => $attributeValue->getAttribute()->getName(),
                'type' => $attributeValue->getAttribute()->getType(),
                'value' => $attributeValue->getValue(),
                'display_value' => $attributeValue->getDisplayValue(),
                'option_id' => $attributeValue->getOption() ? $attributeValue->getOption()->getId() : null,
            ];
        }
        $productData['attributes'] = $attributes;

        // Add tags
        $tags = [];
        foreach ($product->getTags() as $tag) {
            $tags[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'slug' => $tag->getSlug(),
            ];
        }
        $productData['tags'] = $tags;

        // Add variations summary for configurable products
        if ($product->isConfigurable()) {
            $variations = [];
            foreach ($product->getVariations() as $variation) {
                $variations[] = [
                    'id' => $variation->getId(),
                    'sku' => $variation->getSku(),
                    'price' => $variation->getPrice(),
                    'special_price' => $variation->getSpecialPrice(),
                    'active' => $variation->isActive(),
                    'stock_qty' => $variation->getInventories()->count() > 0 ?
                        array_sum(array_map(
                            fn($inventory) => $inventory->getAvailableQuantity(),
                            $variation->getInventories()->toArray()
                        )) : 0,
                ];
            }
            $productData['variations'] = $variations;

            // Add configurable options
            $options = [];
            foreach ($product->getConfigurableOptions() as $option) {
                $optionData = [
                    'id' => $option->getId(),
                    'attribute_id' => $option->getAttribute()->getId(),
                    'attribute_code' => $option->getAttribute()->getCode(),
                    'attribute_name' => $option->getAttribute()->getName(),
                    'input_type' => $option->getInputType(),
                    'required' => $option->isRequired(),
                    'position' => $option->getPosition(),
                    'values' => [],
                ];

                foreach ($option->getValues() as $value) {
                    $optionData['values'][] = [
                        'id' => $value->getId(),
                        'option_id' => $value->getOption()->getId(),
                        'label' => $value->getOption()->getLabel(),
                        'value' => $value->getOption()->getValue(),
                        'price_adjustment' => $value->getPriceAdjustment(),
                        'price_type' => $value->getPriceType(),
                        'weight_adjustment' => $value->getWeightAdjustment(),
                        'is_default' => $value->isDefault(),
                        'position' => $value->getPosition(),
                    ];
                }

                $options[] = $optionData;
            }
            $productData['configurable_options'] = $options;
        }

        // Add related products
        $relatedProducts = [];
        foreach ($product->getRelatedTo() as $related) {
            $relatedProducts[] = [
                'id' => $related->getId(),
                'name' => $related->getName(),
                'sku' => $related->getSku(),
            ];
        }
        $productData['related_products'] = $relatedProducts;

        // Add cross-sell products
        $crossSellProducts = [];
        foreach ($product->getCrossSells() as $crossSell) {
            $crossSellProducts[] = [
                'id' => $crossSell->getId(),
                'name' => $crossSell->getName(),
                'sku' => $crossSell->getSku(),
            ];
        }
        $productData['cross_sell_products'] = $crossSellProducts;

        // Add up-sell products
        $upSellProducts = [];
        foreach ($product->getUpSells() as $upSell) {
            $upSellProducts[] = [
                'id' => $upSell->getId(),
                'name' => $upSell->getName(),
                'sku' => $upSell->getSku(),
            ];
        }
        $productData['up_sell_products'] = $upSellProducts;

        return $productData;
    }

}