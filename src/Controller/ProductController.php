<?php

namespace App\Controller;

use App\DTO\Catalog\ProductDTO;
use App\DTO\Response\ApiResponse;
use App\DTO\Response\ResponsePaginator;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\CacheService;
use App\Service\ProductService;
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

#[Route('/api/products', name: 'api_products_')]
final class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private ProductService $productService,
        private CacheService $cacheService,
    ){}

    /**
     * Create Product
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse{
        $data = json_decode($request->getContent());
        $dto = new ProductDTO();

        $dto->name = $data['name'] ?? '';
        $dto->sku = $data['sku'] ?? '';
        $dto->price = $data['price'] ?? 0;
        $dto->specialPrice = $data['specialPrice'] ?? null;
        $dto->active = $data['active'] ?? true;
        $dto->type = $data['type'] ?? 'simple';
        $dto->categories = $data['categories'] ?? [];
        $dto->tags = $data['tags'] ?? [];

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->json(ApiResponse::error('Validation failed', $errorMessages));
        }

        $product = $this->productService->create($dto);

        return new JsonResponse(
            ApiResponse::success([
                'id' => $product->getId(),
                'name' => $product->getName(),
            ], 'Product created successfully')
        );

    }

    /**
     * Get list of products with pagination
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(50, $request->query->getInt('limit', 10)));
        $categoryId = $request->query->get('category');
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sortBy', 'id');
        $sortOrder = $request->query->get('sortOrder', 'ASC');

        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'ASC';
        }

        // Cache key includes all query parameters to ensure different results are cached separately
        $cacheKey = sprintf(
            'products_list_page_%d_limit_%d_category_%s_search_%s_sort_%s_%s',
            $page, $limit, $categoryId ?? 'all', $search ?? 'none', $sortBy, $sortOrder
        );


        $response = $this->cacheService->get(
            $cacheKey,
            function () use ($page, $limit, $categoryId, $search, $sortBy, $sortOrder) {
                $qb = $this->productRepository->createQueryBuilderWithFilters(
                    categoryId: $categoryId,
                    search: $search,
                    sortBy: $sortBy,
                    sortOrder: $sortOrder,
                    activeOnly: true
                );

                $adapter = new QueryAdapter($qb);
                $pager = Pagerfanta::createForCurrentPageWithMaxPerPage($adapter, $page, $limit);

                $items = [];
                foreach ($pager->getCurrentPageResults() as $product) {
                    $items[] = $this->formatProductData($product);
                }

                return ResponsePaginator::paginated(
                    $items,
                    $page,
                    $pager->getNbResults(),
                    $limit
                );
            },
            300,
            false,
            ["product", "product_page"]
        );

        return new JsonResponse($response);
    }

    /**
     * Get product details by ID
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return new JsonResponse(
                ApiResponse::error('Product not found')->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(
            ApiResponse::success($this->formatProductData($product))->toArray()
        );
    }

    /**
     * Get product details by SKU
     */
    #[Route('/sku/{sku}', name: 'get_by_sku', methods: ['GET'])]
    public function getBySku(string $sku): JsonResponse
    {
        $cacheKey = 'product_sku_' . $sku;

        return new JsonResponse(
            $this->cacheService->get(
                $cacheKey,
                function() use ($sku) {
                    $product = $this->productService->findBySku($sku);

                    if (!$product) {
                        return ApiResponse::error('Product not found', ['sku' => 'Product with this SKU does not exist'])->toArray();
                    }

                    return ApiResponse::success(
                        $this->formatProductData($product, true)
                    )->toArray();
                },
                3600 // 1 hour cache
            ),
            $this->productService->findBySku($sku) ? Response::HTTP_OK : Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Get products by category
     */
    #[Route('/category/{categoryId}', name: 'by_category', methods: ['GET'])]
    public function getByCategory(int $categoryId, Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(50, $request->query->getInt('limit', 10)));
        $sortBy = $request->query->get('sort_by', 'id');
        $sortOrder = strtoupper($request->query->get('sort_order', 'ASC'));

        // Validate sort order
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'ASC';
        }

        $category = $categoryRepository->find($categoryId);
        if (!$category) {
            return new JsonResponse(
                ApiResponse::error('Category not found', ['category_id' => 'Category with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        $cacheKey = sprintf(
            'products_category_%d_page_%d_limit_%d_sort_%s_%s',
            $categoryId,
            $page,
            $limit,
            $sortBy,
            $sortOrder
        );

        return new JsonResponse(
            $this->cacheService->get(
                $cacheKey,
                function() use ($categoryId, $page, $limit, $sortBy, $sortOrder, $category) {
                    $queryBuilder = $this->productRepository->createQueryBuilderByCategory(
                        $categoryId,
                        $sortBy,
                        $sortOrder,
                        true
                    );

                    // Create paginator
                    $adapter = new QueryAdapter($queryBuilder);
                    $pagerfanta = Pagerfanta::createForCurrentPageWithMaxPerPage(
                        $adapter,
                        $page,
                        $limit
                    );

                    $products = [];
                    foreach ($pagerfanta->getCurrentPageResults() as $product) {
                        $products[] = $this->formatProductData($product);
                    }

                    return ApiResponse::success([
                        'category' => [
                            'id' => $category->getId(),
                            'name' => $category->getName(),
                            'slug' => $category->getSlug(),
                        ],
                        'products' => $products,
                        'pagination' => [
                            'current_page' => $pagerfanta->getCurrentPage(),
                            'per_page' => $pagerfanta->getMaxPerPage(),
                            'total_items' => $pagerfanta->getNbResults(),
                            'total_pages' => $pagerfanta->getNbPages(),
                            'has_previous_page' => $pagerfanta->hasPreviousPage(),
                            'has_next_page' => $pagerfanta->hasNextPage(),
                        ]
                    ])->toArray();
                },
                1800 // 30 minutes cache
            )
        );
    }

    /**
     * Get featured products
     */
    #[Route('/featured', name: 'featured', methods: ['GET'])]
    public function getFeatured(Request $request): JsonResponse
    {
        $limit = max(1, min(50, $request->query->getInt('limit', 10)));

        $cacheKey = 'products_featured_limit_' . $limit;

        return new JsonResponse(
            $this->cacheService->get(
                $cacheKey,
                function() use ($limit) {
                    $products = $this->productRepository->findFeaturedProducts($limit);

                    $productData = [];
                    foreach ($products as $product) {
                        $productData[] = $this->formatProductData($product);
                    }

                    return ApiResponse::success([
                        'products' => $productData,
                    ])->toArray();
                },
                3600 // 1 hour cache
            )
        );
    }

    /**
     * Get related products for a product
     */
    #[Route('/{id}/related', name: 'related', methods: ['GET'])]
    public function getRelated(int $id, Request $request): JsonResponse
    {
        $limit = max(1, min(20, $request->query->getInt('limit', 6)));

        $cacheKey = sprintf('products_related_%d_limit_%d', $id, $limit);

        return new JsonResponse(
            $this->cacheService->get(
                $cacheKey,
                function() use ($id, $limit) {
                    $product = $this->productService->findById($id);

                    if (!$product) {
                        return ApiResponse::error('Product not found', ['id' => 'Product with this ID does not exist'])->toArray();
                    }

                    $relatedProducts = [];

                    // First try to get manually defined related products
                    $manually = $product->getRelatedTo()->toArray();

                    // If there aren't enough, get products from the same categories
                    if (count($manually) < $limit) {
                        $categoryProducts = $this->productRepository->findRelatedByCategoryExcluding(
                            $product,
                            $limit - count($manually)
                        );

                        $relatedProducts = array_merge($manually, $categoryProducts);
                    } else {
                        $relatedProducts = array_slice($manually, 0, $limit);
                    }

                    $productData = [];
                    foreach ($relatedProducts as $relatedProduct) {
                        $productData[] = $this->formatProductData($relatedProduct);
                    }

                    return ApiResponse::success([
                        'products' => $productData,
                    ])->toArray();
                },
                3600 // 1 hour cache
            ),
            $this->productService->findById($id) ? Response::HTTP_OK : Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Get product variations
     */
    #[Route('/{id}/variations', name: 'variations', methods: ['GET'])]
    public function getVariations(int $id): JsonResponse
    {
        $cacheKey = 'product_variations_' . $id;

        return new JsonResponse(
            $this->cacheService->get(
                $cacheKey,
                function() use ($id) {
                    $product = $this->productService->findById($id);

                    if (!$product) {
                        return ApiResponse::error('Product not found', ['id' => 'Product with this ID does not exist'])->toArray();
                    }

                    if (!$product->isConfigurable()) {
                        return ApiResponse::error('Not a configurable product', ['type' => 'This product does not have variations'])->toArray();
                    }

                    $variations = [];
                    foreach ($product->getVariations() as $variation) {
                        if (!$variation->isActive()) {
                            continue;
                        }

                        $variationData = [
                            'id' => $variation->getId(),
                            'sku' => $variation->getSku(),
                            'price' => $variation->getPrice(),
                            'formatted_price' => $this->productService->getFormattedPrice($variation),
                            'special_price' => $variation->getSpecialPrice(),
                            'has_special_price' => $variation->hasValidSpecialPrice(),
                            'attributes' => []
                        ];

                        foreach ($variation->getAttributeValues() as $attributeValue) {
                            $variationData['attributes'][] = [
                                'attribute_code' => $attributeValue->getAttribute()->getCode(),
                                'attribute_name' => $attributeValue->getAttribute()->getName(),
                                'value' => $attributeValue->getValue(),
                                'value_label' => $attributeValue->getDisplayValue(),
                            ];
                        }

                        $variations[] = $variationData;
                    }

                    return ApiResponse::success([
                        'product_id' => $product->getId(),
                        'product_name' => $product->getName(),
                        'variations' => $variations,
                    ])->toArray();
                },
                1800 // 30 minutes cache
            ),
            $this->productService->findById($id) ? Response::HTTP_OK : Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Format product data for API response
     */
    private function formatProductData(Product $product, bool $detailed = false): array
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
            'currency_code' => $product->getCurrencyCode() ?? 'USD',
            'is_in_stock' => $this->productService->isAvailableForPurchase($product),
        ];

        // Add image
        $defaultImage = $product->getDefaultImage();
        $productData['image'] = $defaultImage ? $defaultImage->getPath() : null;

        // Add basic category info
        if ($product->getCategories()->count() > 0) {
            $mainCategory = $product->getCategories()->first();
            $productData['category'] = [
                'id' => $mainCategory->getId(),
                'name' => $mainCategory->getName(),
                'slug' => $mainCategory->getSlug(),
            ];
        }

        // For detailed view add more information
        if ($detailed) {
            $productData['short_description'] = $product->getShortDescription();
            $productData['description'] = $product->getDescription();
            $productData['weight'] = $product->getWeight();
            $productData['is_featured'] = $product->isFeatured();

            // Add all categories
            $categories = [];
            foreach ($product->getCategories() as $category) {
                $categories[] = [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'slug' => $category->getSlug(),
                ];
            }
            $productData['categories'] = $categories;

            // Add all images
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
                    'code' => $attributeValue->getAttribute()->getCode(),
                    'name' => $attributeValue->getAttribute()->getName(),
                    'value' => $attributeValue->getValue(),
                    'display_value' => $attributeValue->getDisplayValue(),
                ];
            }
            $productData['attributes'] = $attributes;

            // For configurable products, add configuration options
            if ($product->isConfigurable()) {
                $options = [];
                foreach ($product->getConfigurableOptions() as $option) {
                    $optionData = [
                        'id' => $option->getId(),
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
                            'label' => $value->getOption()->getLabel(),
                            'value' => $value->getOption()->getValue(),
                            'price_adjustment' => $value->getPriceAdjustment(),
                            'price_type' => $value->getPriceType(),
                            'is_default' => $value->isDefault(),
                        ];
                    }

                    $options[] = $optionData;
                }
                $productData['configurable_options'] = $options;
                $productData['has_variations'] = $product->getVariations()->count() > 0;
            }

            // Add inventory information
            try {
                $stockInfo = $this->productService->getStockInfo($product);
                $productData['stock_info'] = [
                    'qty' => $stockInfo['available_quantity'] ?? 0,
                    'is_in_stock' => $stockInfo['has_stock'] ?? false,
                    'backorders_allowed' => $stockInfo['backorders_allowed'] ?? false,
                    'status' => $stockInfo['status'] ?? 'out_of_stock',
                ];
            } catch (\Exception $e) {
                // Default stock info if inventory service is unavailable
                $productData['stock_info'] = [
                    'qty' => 0,
                    'is_in_stock' => false,
                    'backorders_allowed' => false,
                    'status' => 'out_of_stock',
                ];
            }

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
        }

        return $productData;
    }
}
