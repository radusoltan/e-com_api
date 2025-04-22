<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\DTO\Response\ApiResponse;
use App\Entity\Attribute;
use App\Entity\AttributeOption;
use App\Repository\AttributeRepository;
use App\Repository\AttributeOptionRepository;
use App\Service\CacheService;
use App\Service\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/attributes', name: 'api_admin_attributes_')]
final class AttributeController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttributeRepository $attributeRepository,
        private AttributeOptionRepository $attributeOptionRepository,
        private CacheService $cacheService,
        private PermissionService $permissionService,
        private ValidatorInterface $validator,
    ) {}

    /**
     * Get list of attributes with pagination
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to view attributes'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $search = $request->query->get('search');
        $type = $request->query->get('type');
        $sortBy = $request->query->get('sort_by', 'id');
        $sortOrder = strtoupper($request->query->get('sort_order', 'ASC'));

        // Validate sort order
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'ASC';
        }

        // Build query
        $queryBuilder = $this->attributeRepository->createQueryBuilder('a');

        // Apply search filter
        if (!empty($search)) {
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->like('a.name', ':search'),
                        $queryBuilder->expr()->like('a.code', ':search'),
                        $queryBuilder->expr()->like('a.description', ':search')
                    )
                )
                ->setParameter('search', '%' . $search . '%');
        }

        // Apply type filter
        if (!empty($type)) {
            $queryBuilder
                ->andWhere('a.type = :type')
                ->setParameter('type', $type);
        }

        // Validate sort field to prevent SQL injection
        $allowedSortFields = [
            'id', 'name', 'code', 'type', 'position'
        ];

        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'id'; // Default to ID if invalid sort field
        }

        // Add sorting
        $queryBuilder->orderBy('a.' . $sortBy, $sortOrder);

        // Create paginator
        $adapter = new QueryAdapter($queryBuilder);
        $pagerfanta = Pagerfanta::createForCurrentPageWithMaxPerPage(
            $adapter,
            $page,
            $limit
        );

        $attributes = [];
        foreach ($pagerfanta->getCurrentPageResults() as $attribute) {
            $attributes[] = $this->formatAttributeData($attribute);
        }

        return new JsonResponse(
            ApiResponse::success([
                'attributes' => $attributes,
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
     * Get attribute details by ID
     */
    #[Route('/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getById(int $id): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to view attributes'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $attribute = $this->attributeRepository->find($id);

        if (!$attribute) {
            return new JsonResponse(
                ApiResponse::error('Attribute not found', ['id' => 'Attribute with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(
            ApiResponse::success($this->formatAttributeData($attribute))->toArray()
        );
    }

    /**
     * Create a new attribute
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to create attributes'])->toArray(),
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

        // Create new attribute
        $attribute = new Attribute();

        // Set attribute data
        $attribute->setName($data['name'] ?? '');
        $attribute->setCode($data['code'] ?? strtolower(str_replace(' ', '_', $data['name'] ?? '')));
        $attribute->setType($data['type'] ?? Attribute::TYPE_TEXT);
        $attribute->setFrontendInput($data['frontend_input'] ?? Attribute::FRONTEND_INPUT_TEXT);
        $attribute->setDescription($data['description'] ?? null);
        $attribute->setRequired(isset($data['required']) ? (bool)$data['required'] : false);
        $attribute->setFilterable(isset($data['filterable']) ? (bool)$data['filterable'] : false);
        $attribute->setSearchable(isset($data['searchable']) ? (bool)$data['searchable'] : false);
        $attribute->setComparable(isset($data['comparable']) ? (bool)$data['comparable'] : false);
        $attribute->setVisibleInProductListing(isset($data['visible_in_product_listing']) ? (bool)$data['visible_in_product_listing'] : false);
        $attribute->setVisibleOnProductPage(isset($data['visible_on_product_page']) ? (bool)$data['visible_on_product_page'] : true);
        $attribute->setUsedInProductConfigurator(isset($data['used_in_product_configurator']) ? (bool)$data['used_in_product_configurator'] : false);
        $attribute->setPosition($data['position'] ?? 0);
        $attribute->setActive(isset($data['active']) ? (bool)$data['active'] : true);

        // Validate attribute
        $errors = $this->validator->validate($attribute);
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

        // Save attribute
        $this->entityManager->persist($attribute);
        $this->entityManager->flush();

        // Add options if provided for select/multiselect attributes
        if (in_array($attribute->getType(), [Attribute::TYPE_SELECT, Attribute::TYPE_MULTISELECT]) &&
            isset($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $position => $optionData) {
                $option = new AttributeOption();
                $option->setAttribute($attribute);
                $option->setValue($optionData['value'] ?? '');
                $option->setLabel($optionData['label'] ?? $optionData['value'] ?? '');
                $option->setPosition($position);

                if (isset($optionData['color'])) {
                    $option->setColor($optionData['color']);
                }

                if (isset($optionData['image'])) {
                    $option->setImage($optionData['image']);
                }

                $this->entityManager->persist($option);
                $attribute->addOption($option);
            }

            $this->entityManager->flush();
        }

        // Invalidate cache
        $this->cacheService->invalidateTag('attributes');

        return new JsonResponse(
            ApiResponse::success(
                $this->formatAttributeData($attribute),
                'Attribute created successfully'
            )->toArray(),
            Response::HTTP_CREATED
        );
    }

    /**
     * Update an existing attribute
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to edit attributes'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $attribute = $this->attributeRepository->find($id);

        if (!$attribute) {
            return new JsonResponse(
                ApiResponse::error('Attribute not found', ['id' => 'Attribute with this ID does not exist'])->toArray(),
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

        // Update attribute data
        if (isset($data['name'])) {
            $attribute->setName($data['name']);
        }

        // Only allow updating code if no products are using it yet
        if (isset($data['code']) && count($attribute->getProductAttributeValues()) === 0) {
            // Check if code is already used by another attribute
            $existingAttribute = $this->attributeRepository->findOneBy(['code' => $data['code']]);
            if ($existingAttribute && $existingAttribute->getId() !== $attribute->getId()) {
                return new JsonResponse(
                    ApiResponse::error('Validation failed', ['code' => 'This code is already in use'])->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $attribute->setCode($data['code']);
        }

        if (isset($data['description'])) {
            $attribute->setDescription($data['description']);
        }

        if (isset($data['frontend_input'])) {
            $attribute->setFrontendInput($data['frontend_input']);
        }

        if (isset($data['required'])) {
            $attribute->setRequired((bool)$data['required']);
        }

        if (isset($data['filterable'])) {
            $attribute->setFilterable((bool)$data['filterable']);
        }

        if (isset($data['searchable'])) {
            $attribute->setSearchable((bool)$data['searchable']);
        }

        if (isset($data['comparable'])) {
            $attribute->setComparable((bool)$data['comparable']);
        }

        if (isset($data['visible_in_product_listing'])) {
            $attribute->setVisibleInProductListing((bool)$data['visible_in_product_listing']);
        }

        if (isset($data['visible_on_product_page'])) {
            $attribute->setVisibleOnProductPage((bool)$data['visible_on_product_page']);
        }

        if (isset($data['used_in_product_configurator'])) {
            $attribute->setUsedInProductConfigurator((bool)$data['used_in_product_configurator']);
        }

        if (isset($data['position'])) {
            $attribute->setPosition($data['position']);
        }

        if (isset($data['active'])) {
            $attribute->setActive((bool)$data['active']);
        }

        // Update timestamp
        $attribute->setUpdatedAt(new \DateTimeImmutable());

        // Validate attribute
        $errors = $this->validator->validate($attribute);
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

        // Save attribute
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('attributes');

        return new JsonResponse(
            ApiResponse::success(
                $this->formatAttributeData($attribute),
                'Attribute updated successfully'
            )->toArray()
        );
    }

    /**
     * Delete an attribute
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to delete attributes'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $attribute = $this->attributeRepository->find($id);

        if (!$attribute) {
            return new JsonResponse(
                ApiResponse::error('Attribute not found', ['id' => 'Attribute with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        // Check if attribute is in use by any products
        if (count($attribute->getProductAttributeValues()) > 0) {
            return new JsonResponse(
                ApiResponse::error('Cannot delete attribute', ['in_use' => 'This attribute is in use by products'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Perform the delete
        $this->entityManager->remove($attribute);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('attributes');

        return new JsonResponse(
            ApiResponse::success(null, 'Attribute deleted successfully')->toArray()
        );
    }

    /**
     * Get attribute options
     */
    #[Route('/{id}/options', name: 'options', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getOptions(int $id): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_view')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to view attributes'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $attribute = $this->attributeRepository->find($id);

        if (!$attribute) {
            return new JsonResponse(
                ApiResponse::error('Attribute not found', ['id' => 'Attribute with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$attribute->hasOptions()) {
            return new JsonResponse(
                ApiResponse::error('Attribute has no options', ['type' => 'This attribute type does not support options'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $options = [];
        foreach ($attribute->getOptions() as $option) {
            $options[] = [
                'id' => $option->getId(),
                'value' => $option->getValue(),
                'label' => $option->getLabel(),
                'position' => $option->getPosition(),
                'color' => $option->getColor(),
                'image' => $option->getImage(),
                'active' => $option->isActive(),
            ];
        }

        return new JsonResponse(
            ApiResponse::success([
                'attribute_id' => $attribute->getId(),
                'attribute_code' => $attribute->getCode(),
                'attribute_name' => $attribute->getName(),
                'options' => $options,
            ])->toArray()
        );
    }

    /**
     * Add a new option to an attribute
     */
    #[Route('/{id}/options', name: 'add_option', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addOption(int $id, Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to edit attributes'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $attribute = $this->attributeRepository->find($id);

        if (!$attribute) {
            return new JsonResponse(
                ApiResponse::error('Attribute not found', ['id' => 'Attribute with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$attribute->hasOptions()) {
            return new JsonResponse(
                ApiResponse::error('Attribute has no options', ['type' => 'This attribute type does not support options'])->toArray(),
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

        // Create new option
        $option = new AttributeOption();
        $option->setAttribute($attribute);
        $option->setValue($data['value'] ?? '');
        $option->setLabel($data['label'] ?? $data['value'] ?? '');
        $option->setPosition($data['position'] ?? count($attribute->getOptions()) + 1);

        if (isset($data['color'])) {
            $option->setColor($data['color']);
        }

        if (isset($data['image'])) {
            $option->setImage($data['image']);
        }

        $option->setActive(isset($data['active']) ? (bool)$data['active'] : true);

        // Validate option
        $errors = $this->validator->validate($option);
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

        // Save option
        $this->entityManager->persist($option);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('attributes');

        return new JsonResponse(
            ApiResponse::success(
                [
                    'id' => $option->getId(),
                    'value' => $option->getValue(),
                    'label' => $option->getLabel(),
                    'position' => $option->getPosition(),
                    'color' => $option->getColor(),
                    'image' => $option->getImage(),
                    'active' => $option->isActive(),
                ],
                'Option added successfully'
            )->toArray(),
            Response::HTTP_CREATED
        );
    }

    /**
     * Update an attribute option
     */
    #[Route('/options/{id}', name: 'update_option', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateOption(int $id, Request $request): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to edit attributes'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $option = $this->attributeOptionRepository->find($id);

        if (!$option) {
            return new JsonResponse(
                ApiResponse::error('Option not found', ['id' => 'Option with this ID does not exist'])->toArray(),
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

        // Update option data
        if (isset($data['value'])) {
            $option->setValue($data['value']);
        }

        if (isset($data['label'])) {
            $option->setLabel($data['label']);
        }

        if (isset($data['position'])) {
            $option->setPosition($data['position']);
        }

        if (isset($data['color'])) {
            $option->setColor($data['color']);
        }

        if (isset($data['image'])) {
            $option->setImage($data['image']);
        }

        if (isset($data['active'])) {
            $option->setActive((bool)$data['active']);
        }

        // Update timestamp
        $option->setUpdatedAt(new \DateTimeImmutable());

        // Validate option
        $errors = $this->validator->validate($option);
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

        // Save option
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('attributes');

        return new JsonResponse(
            ApiResponse::success(
                [
                    'id' => $option->getId(),
                    'value' => $option->getValue(),
                    'label' => $option->getLabel(),
                    'position' => $option->getPosition(),
                    'color' => $option->getColor(),
                    'image' => $option->getImage(),
                    'active' => $option->isActive(),
                ],
                'Option updated successfully'
            )->toArray()
        );
    }

    /**
     * Delete an attribute option
     */
    #[Route('/options/{id}', name: 'delete_option', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteOption(int $id): JsonResponse
    {
        // Check permission
        if (!$this->permissionService->hasPermission('product_edit')) {
            return new JsonResponse(
                ApiResponse::error('Access denied', ['permission' => 'You do not have permission to edit attributes'])->toArray(),
                Response::HTTP_FORBIDDEN
            );
        }

        $option = $this->attributeOptionRepository->find($id);

        if (!$option) {
            return new JsonResponse(
                ApiResponse::error('Option not found', ['id' => 'Option with this ID does not exist'])->toArray(),
                Response::HTTP_NOT_FOUND
            );
        }

        // Check if option is in use by any products
        if (count($option->getProductAttributeValues()) > 0) {
            return new JsonResponse(
                ApiResponse::error('Cannot delete option', ['in_use' => 'This option is in use by products'])->toArray(),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Perform the delete
        $this->entityManager->remove($option);
        $this->entityManager->flush();

        // Invalidate cache
        $this->cacheService->invalidateTag('attributes');

        return new JsonResponse(
            ApiResponse::success(null, 'Option deleted successfully')->toArray()
        );
    }

    /**
     * Format attribute data for API response
     */
    private function formatAttributeData(Attribute $attribute): array
    {
        $data = [
            'id' => $attribute->getId(),
            'name' => $attribute->getName(),
            'code' => $attribute->getCode(),
            'type' => $attribute->getType(),
            'frontend_input' => $attribute->getFrontendInput(),
            'description' => $attribute->getDescription(),
            'required' => $attribute->isRequired(),
            'filterable' => $attribute->isFilterable(),
            'searchable' => $attribute->isSearchable(),
            'comparable' => $attribute->isComparable(),
            'visible_in_product_listing' => $attribute->isVisibleInProductListing(),
            'visible_on_product_page' => $attribute->isVisibleOnProductPage(),
            'used_in_product_configurator' => $attribute->isUsedInProductConfigurator(),
            'position' => $attribute->getPosition(),
            'active' => $attribute->isActive(),
            'has_options' => $attribute->hasOptions(),
            'created_at' => $attribute->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $attribute->getUpdatedAt() ?
                $attribute->getUpdatedAt()->format(\DateTimeInterface::ATOM) : null,
        ];

        // Add options count if attribute has options
        if ($attribute->hasOptions()) {
            $data['options_count'] = $attribute->getOptions()->count();

            // Include first few options for preview
            $previewOptions = [];
            $i = 0;
            foreach ($attribute->getOptions() as $option) {
                if ($i >= 5) {
                    break;
                }

                $previewOptions[] = [
                    'id' => $option->getId(),
                    'value' => $option->getValue(),
                    'label' => $option->getLabel(),
                ];

                $i++;
            }

            $data['option_preview'] = $previewOptions;
        }

        return $data;
    }

}