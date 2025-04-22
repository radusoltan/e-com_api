<?php

namespace App\Controller\Admin;

use App\DTO\Response\ApiResponse;
use App\Entity\Product;
use App\Entity\ProductVariation;
use App\Entity\ProductVariationAttributeValue;
use App\Repository\AttributeOptionRepository;
use App\Repository\AttributeRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariationRepository;
use App\Service\CacheService;
use App\Service\PermissionService;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/variations', name: 'api_admin_variations_')]
final class ProductVariationController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private ProductVariationRepository $variationRepository,
        private AttributeRepository $attributeRepository,
        private AttributeOptionRepository $attributeOptionRepository,
        private ProductService $productService,
        private CacheService $cacheService,
        private PermissionService $permissionService,
        private ValidatorInterface $validator,
    ) {}

    /**
     * Get all variations for a product
     */
    #[Route('/{productId}/variations', name: 'list', methods: ['GET'], requirements: ['productId' => '\d+'])]
    public function getVariations(int $productId): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to view products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $product = $this->productRepository->find($productId);

        if (!$product) {
            return new JsonResponse(
                ApiResponse::error('Product not found', ['id' => 'Product with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$product->isConfigurable()) {
            return new JsonResponse(
                ApiResponse::error('Not a configurable product', ['type' => 'This product does not support variations'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $variations = [];
        foreach ($product->getVariations() as $variation) {
            $variations[] = $this->formatVariationData($variation);
        }

        return new JsonResponse(
            ApiResponse::success([
                'product_id' => $product->getId(),
                'product_name' => $product->getName(),
                'product_sku' => $product->getSku(),
                'variations' => $variations,
            ])->toArray()
        );
    }

    /**
     * Get a specific variation
     */
    #[Route('/variations/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getVariation(int $id): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to view products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $variation = $this->variationRepository->find($id);

        if (!$variation) {
            return new JsonResponse(
                ApiResponse::error('Variation not found', ['id' => 'Variation with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(
            ApiResponse::success($this->formatVariationData($variation))->toArray()
        );
    }

    /**
     * Create a new variation for a product
     */
    #[Route('/{productId}/variations', name: 'create', methods: ['POST'], requirements: ['productId' => '\d+'])]
    public function createVariation(int $productId, Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to edit products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $product = $this->productRepository->find($productId);

        if (!$product) {
            return new JsonResponse(
                ApiResponse::error('Product not found', ['id' => 'Product with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$product->isConfigurable()) {
            return new JsonResponse(
                ApiResponse::error('Not a configurable product', ['type' => 'This product does not support variations'])->toArray(),
                Response::HTTP_BAD_REQUEST
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

        // Create new variation
        $variation = new ProductVariation();
        $variation->setParent($product);

        // Set basic variation data
        $variation->setSku($data['sku'] ?? $this->productService->generateUniqueSku($product->getSku() . '-'));
        $variation->setPrice($data['price'] ?? $product->getPrice());
        $variation->setWeight($data['weight'] ?? $product->getWeight());
        $variation->setActive($data['active'] ?? true);

        // Set special price if provided
        if (isset($data['special_price'])) {
            $variation->setSpecialPrice($data['special_price']);

            if (isset($data['special_price_from'])) {
                $variation->setSpecialPriceFrom(new \DateTimeImmutable($data['special_price_from']));
            }

            if (isset($data['special_price_to'])) {
                $variation->setSpecialPriceTo(new \DateTimeImmutable($data['special_price_to']));
            }
        }

        // Set attribute values if provided
        if (isset($data['attribute_values']) && is_array($data['attribute_values'])) {
            foreach ($data['attribute_values'] as $attributeValueData) {
                if (!isset($attributeValueData['attribute_id']) || !isset($attributeValueData['option_id'])) {
                    continue;
                }

                $attribute = $this->attributeRepository->find($attributeValueData['attribute_id']);
                $option = $this->attributeOptionRepository->find($attributeValueData['option_id']);

                if (!$attribute || !$option) {
                    continue;
                }

                $attributeValue = new ProductVariationAttributeValue();
                $attributeValue->setVariation($variation);
                $attributeValue->setAttribute($attribute);
                $attributeValue->setOption($option);
                $attributeValue->setValue($option->getValue());

                $this->entityManager->persist($attributeValue);
                $variation->addAttributeValue($attributeValue);
            }
        }

        // Validate variation
        $errors = $this->validator->validate($variation);
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

        // Save variation
        $this->entityManager->persist($variation);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('products');
        $this->cacheService->delete('product_detail_' . $product->getId());
        $this->cacheService->delete('product_variations_' . $product->getId());

        return new JsonResponse(
            ApiResponse::success(
                $this->formatVariationData($variation),
                'Variation created successfully'
            )->toArray(),
            Response::HTTP_CREATED
        );
    }

    /**
     * Update a variation
     */
    #[Route('/variations/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateVariation(int $id, Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to edit products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $variation = $this->variationRepository->find($id);

        if (!$variation) {
            return new JsonResponse(
                ApiResponse::error('Variation not found', ['id' => 'Variation with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        $product = $variation->getParent();

        // Parse request data
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(
                ApiResponse::error('Invalid JSON data')->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Update variation data
        if (isset($data['sku'])) {
            // Check if SKU is already used by another variation
            $existingVariation = $this->variationRepository->findOneBy(['sku' => $data['sku']]);
            if ($existingVariation && $existingVariation->getId() !== $variation->getId()) {
                return new JsonResponse(
                    ApiResponse::error('Validation failed', ['sku' => 'This SKU is already in use'])->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $variation->setSku($data['sku']);
        }

        if (isset($data['price'])) {
            $variation->setPrice($data['price']);
        }

        if (isset($data['weight'])) {
            $variation->setWeight($data['weight']);
        }

        if (isset($data['active'])) {
            $variation->setActive((bool)$data['active']);
        }

        // Update special price fields
        if (isset($data['special_price'])) {
            $variation->setSpecialPrice($data['special_price']);
        }

        if (isset($data['special_price_from'])) {
            $variation->setSpecialPriceFrom(
                $data['special_price_from'] ? new \DateTimeImmutable($data['special_price_from']) : null
            );
        }

        if (isset($data['special_price_to'])) {
            $variation->setSpecialPriceTo(
                $data['special_price_to'] ? new \DateTimeImmutable($data['special_price_to']) : null
            );
        }

        // Update timestamp
        $variation->setUpdatedAt(new \DateTimeImmutable());

        // Validate variation
        $errors = $this->validator->validate($variation);
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

        // Save variation
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('products');
        $this->cacheService->delete('product_detail_' . $product->getId());
        $this->cacheService->delete('product_variations_' . $product->getId());

        return new JsonResponse(
            ApiResponse::success(
                $this->formatVariationData($variation),
                'Variation updated successfully'
            )->toArray()
        );
    }

    /**
     * Delete a variation
     */
    #[Route('/variations/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteVariation(int $id): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to edit products'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $variation = $this->variationRepository->find($id);

        if (!$variation) {
            return new JsonResponse(
                ApiResponse::error('Variation not found', ['id' => 'Variation with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        $productId = $variation->getParent()->getId();

        // Perform the delete
        $this->entityManager->remove($variation);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('products');
        $this->cacheService->delete('product_detail_' . $productId);
        $this->cacheService->delete('product_variations_' . $productId);

        return new JsonResponse(
            ApiResponse::success(null, 'Variation deleted successfully')->toArray()
        );
    }

    /**
     * Format variation data for API response
     */
    private function formatVariationData(ProductVariation $variation): array
    {
        $product = $variation->getParent();

        $variationData = [
            'id' => $variation->getId(),
            'parent_id' => $product->getId(),
            'parent_name' => $product->getName(),
            'sku' => $variation->getSku(),
            'price' => $variation->getPrice(),
            'formatted_price' => $this->productService->getFormattedPrice($variation),
            'special_price' => $variation->getSpecialPrice(),
            'has_special_price' => $variation->hasValidSpecialPrice(),
            'special_price_from' => $variation->getSpecialPriceFrom()?->format(\DateTimeInterface::ATOM),
            'special_price_to' => $variation->getSpecialPriceTo()?->format(\DateTimeInterface::ATOM),
            'weight' => $variation->getWeight(),
            'active' => $variation->isActive(),
            'created_at' => $variation->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $variation->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];

        // Add attribute values
        $attributes = [];
        foreach ($variation->getAttributeValues() as $attributeValue) {
            $attribute = $attributeValue->getAttribute();
            $option = $attributeValue->getOption();

            $attributes[] = [
                'id' => $attributeValue->getId(),
                'attribute_id' => $attribute->getId(),
                'attribute_code' => $attribute->getCode(),
                'attribute_name' => $attribute->getName(),
                'option_id' => $option ? $option->getId() : null,
                'option_value' => $option ? $option->getValue() : null,
                'option_label' => $option ? $option->getLabel() : null,
                'value' => $attributeValue->getValue(),
            ];
        }
        $variationData['attributes'] = $attributes;

        // Add images
        $images = [];
        foreach ($variation->getImages() as $image) {
            $images[] = [
                'id' => $image->getId(),
                'path' => $image->getPath(),
                'alt' => $image->getAlt(),
                'title' => $image->getTitle(),
                'is_default' => $image->isDefault(),
                'position' => $image->getPosition(),
            ];
        }
        $variationData['images'] = $images;

        // Add stock information
        try {
            $stockInfo = $this->productService->getVariationStockInfo($variation);
            $variationData['stock_info'] = [
                'qty' => $stockInfo['available_quantity'] ?? 0,
                'is_in_stock' => $stockInfo['has_stock'] ?? false,
                'backorders_allowed' => $stockInfo['backorders_allowed'] ?? false,
                'status' => $stockInfo['status'] ?? 'out_of_stock',
            ];
        } catch (\Exception $e) {
            // Default stock info if inventory service is unavailable
            $variationData['stock_info'] = [
                'qty' => 0,
                'is_in_stock' => false,
                'backorders_allowed' => false,
                'status' => 'out_of_stock',
            ];
        }

        return $variationData;
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        if (!$this->permissionService->hasPermission('product_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied')->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $variation = $this->variationRepository->find($id);

        if (!$variation) {
            return new JsonResponse(
                ApiResponse::error('Variation not found')->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(
            ApiResponse::success([
                'id' => $variation->getId(),
                'sku' => $variation->getSku(),
                'price' => $variation->getPrice(),
                'special_price' => $variation->getSpecialPrice(),
                'special_from' => $variation->getSpecialPriceFrom()?->format('Y-m-d'),
                'special_to' => $variation->getSpecialPriceTo()?->format('Y-m-d'),
                'weight' => $variation->getWeight(),
                'active' => $variation->isActive(),
                'created_at' => $variation->getCreatedAt()->format('c'),
                'updated_at' => $variation->getUpdatedAt()?->format('c'),
            ])->toArray()
        );
    }

}