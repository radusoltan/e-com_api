<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Create a query builder with filters
     *
     * @param int|null $categoryId Filter by category ID
     * @param string|null $search Search in name and description
     * @param string $sortBy Field to sort by
     * @param string $sortOrder Sort direction (ASC or DESC)
     * @param bool $activeOnly Include only active products
     * @return QueryBuilder
     */
    public function createQueryBuilderWithFilters(
        ?int $categoryId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortOrder = 'ASC',
        bool $activeOnly = true
    ): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('p');

        // Always select the main product entity
        $queryBuilder->select('p');

        // Join with categories if filtering by category
        if ($categoryId !== null) {
            $queryBuilder
                ->innerJoin('p.categories', 'c')
                ->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        // Add search condition if search term is provided
        if ($search !== null && $search !== '') {
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->like('p.name', ':search'),
                        $queryBuilder->expr()->like('p.shortDescription', ':search'),
                        $queryBuilder->expr()->like('p.description', ':search'),
                        $queryBuilder->expr()->like('p.sku', ':search')
                    )
                )
                ->setParameter('search', '%' . $search . '%');
        }

        // Filter by active status if requested
        if ($activeOnly) {
            $queryBuilder
                ->andWhere('p.active = :active')
                ->setParameter('active', true);

            // Also check availability dates if product has them
            $now = new \DateTimeImmutable();
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.availableFrom'),
                    $queryBuilder->expr()->lte('p.availableFrom', ':now')
                )
            )
                ->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->isNull('p.availableTo'),
                        $queryBuilder->expr()->gte('p.availableTo', ':now')
                    )
                )
                ->setParameter('now', $now);
        }

        // Validate sort field to prevent SQL injection
        $allowedSortFields = [
            'id', 'name', 'price', 'createdAt', 'updatedAt', 'sku', 'type'
        ];

        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'id'; // Default to ID if invalid sort field
        }

        // Add sorting
        $queryBuilder->orderBy('p.' . $sortBy, $sortOrder);

        return $queryBuilder;
    }

    /**
     * Create a query builder for products in a specific category
     *
     * @param int $categoryId Category ID
     * @param string $sortBy Field to sort by
     * @param string $sortOrder Sort direction (ASC or DESC)
     * @param bool $activeOnly Include only active products
     * @return QueryBuilder
     */
    public function createQueryBuilderByCategory(
        int $categoryId,
        string $sortBy = 'id',
        string $sortOrder = 'ASC',
        bool $activeOnly = true
    ): QueryBuilder {
        return $this->createQueryBuilderWithFilters(
            categoryId: $categoryId,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
            activeOnly: $activeOnly
        );
    }

    /**
     * Find featured products
     *
     * @param int $limit Maximum number of products to return
     * @return Product[]
     */
    public function findFeaturedProducts(int $limit = 10): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->andWhere('p.featured = :featured')
            ->andWhere('p.active = :active')
            ->setParameter('featured', true)
            ->setParameter('active', true)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit);

        // Check availability dates
        $now = new \DateTimeImmutable();
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('p.availableFrom'),
                $queryBuilder->expr()->lte('p.availableFrom', ':now')
            )
        )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.availableTo'),
                    $queryBuilder->expr()->gte('p.availableTo', ':now')
                )
            )
            ->setParameter('now', $now);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Find products related by category, excluding specified product
     *
     * @param Product $product Product to find related products for
     * @param int $limit Maximum number of products to return
     * @return Product[]
     */
    public function findRelatedByCategoryExcluding(Product $product, int $limit = 4): array
    {
        // Get the categories of the specified product
        $categoryIds = [];
        foreach ($product->getCategories() as $category) {
            $categoryIds[] = $category->getId();
        }

        if (empty($categoryIds)) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->andWhere('c.id IN (:categoryIds)')
            ->andWhere('p.id != :productId')
            ->andWhere('p.active = :active')
            ->setParameter('categoryIds', $categoryIds)
            ->setParameter('productId', $product->getId())
            ->setParameter('active', true)
            ->orderBy('RAND()')
            ->setMaxResults($limit);

        // Check availability dates
        $now = new \DateTimeImmutable();
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('p.availableFrom'),
                $queryBuilder->expr()->lte('p.availableFrom', ':now')
            )
        )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.availableTo'),
                    $queryBuilder->expr()->gte('p.availableTo', ':now')
                )
            )
            ->setParameter('now', $now);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Find products by tag
     *
     * @param string $tagSlug Tag slug
     * @param int $limit Maximum number of products to return
     * @return Product[]
     */
    public function findByTagSlug(string $tagSlug, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.tags', 't')
            ->andWhere('t.slug = :tagSlug')
            ->andWhere('p.active = :active')
            ->setParameter('tagSlug', $tagSlug)
            ->setParameter('active', true)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by multiple SKUs
     *
     * @param array $skus Array of SKUs
     * @return Product[]
     */
    public function findBySkus(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.sku IN (:skus)')
            ->setParameter('skus', $skus)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently added products
     *
     * @param int $limit Maximum number of products to return
     * @return Product[]
     */
    public function findRecentProducts(int $limit = 10): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->andWhere('p.active = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        // Check availability dates
        $now = new \DateTimeImmutable();
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('p.availableFrom'),
                $queryBuilder->expr()->lte('p.availableFrom', ':now')
            )
        )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.availableTo'),
                    $queryBuilder->expr()->gte('p.availableTo', ':now')
                )
            )
            ->setParameter('now', $now);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Find products on sale (with valid special price)
     *
     * @param int $limit Maximum number of products to return
     * @return Product[]
     */
    public function findProductsOnSale(int $limit = 10): array
    {
        $now = new \DateTimeImmutable();

        $queryBuilder = $this->createQueryBuilder('p')
            ->andWhere('p.active = :active')
            ->andWhere('p.specialPrice IS NOT NULL')
            ->andWhere('p.specialPrice < p.price') // Ensure it's actually a discount
            ->setParameter('active', true)

            // Handle special price date range
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.specialPriceFrom'),
                    $queryBuilder->expr()->lte('p.specialPriceFrom', ':now')
                )
            )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.specialPriceTo'),
                    $queryBuilder->expr()->gte('p.specialPriceTo', ':now')
                )
            )
            ->setParameter('now', $now)

            // Check availability dates
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.availableFrom'),
                    $queryBuilder->expr()->lte('p.availableFrom', ':now')
                )
            )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.availableTo'),
                    $queryBuilder->expr()->gte('p.availableTo', ':now')
                )
            )
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Search products by keyword with advanced search functionality
     *
     * @param string $keyword Search keyword
     * @param array $filters Additional filters (category_ids, price_range, attributes)
     * @param string $sortBy Field to sort by
     * @param string $sortOrder Sort direction (ASC or DESC)
     * @return QueryBuilder
     */
    public function createSearchQueryBuilder(
        string $keyword,
        array $filters = [],
        string $sortBy = 'id',
        string $sortOrder = 'ASC'
    ): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('p');

        // Base search condition
        if (!empty($keyword)) {
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->like('p.name', ':keyword'),
                        $queryBuilder->expr()->like('p.shortDescription', ':keyword'),
                        $queryBuilder->expr()->like('p.description', ':keyword'),
                        $queryBuilder->expr()->like('p.sku', ':keyword')
                    )
                )
                ->setParameter('keyword', '%' . $keyword . '%');
        }

        // Only active products
        $queryBuilder
            ->andWhere('p.active = :active')
            ->setParameter('active', true);

        // Check availability dates
        $now = new \DateTimeImmutable();
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.availableFrom'),
                    $queryBuilder->expr()->lte('p.availableFrom', ':now')
                )
            )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('p.availableTo'),
                    $queryBuilder->expr()->gte('p.availableTo', ':now')
                )
            )
            ->setParameter('now', $now);

        // Apply category filter
        if (!empty($filters['category_ids'])) {
            $queryBuilder
                ->innerJoin('p.categories', 'c')
                ->andWhere('c.id IN (:categoryIds)')
                ->setParameter('categoryIds', $filters['category_ids']);
        }

        // Apply price range filter
        if (!empty($filters['price_range'])) {
            $priceRange = $filters['price_range'];

            if (isset($priceRange['min']) && is_numeric($priceRange['min'])) {
                $queryBuilder
                    ->andWhere('p.price >= :minPrice')
                    ->setParameter('minPrice', $priceRange['min'] * 100); // Convert to cents
            }

            if (isset($priceRange['max']) && is_numeric($priceRange['max'])) {
                $queryBuilder
                    ->andWhere('p.price <= :maxPrice')
                    ->setParameter('maxPrice', $priceRange['max'] * 100); // Convert to cents
            }
        }

        // Apply attribute filters
        if (!empty($filters['attributes']) && is_array($filters['attributes'])) {
            $i = 0;
            foreach ($filters['attributes'] as $attributeCode => $value) {
                $alias = 'av' . $i;
                $attrAlias = 'attr' . $i;

                $queryBuilder
                    ->innerJoin('p.attributeValues', $alias)
                    ->innerJoin("{$alias}.attribute", $attrAlias)
                    ->andWhere("{$attrAlias}.code = :attrCode{$i}")
                    ->andWhere("{$alias}.value = :attrValue{$i}")
                    ->setParameter("attrCode{$i}", $attributeCode)
                    ->setParameter("attrValue{$i}", $value);

                $i++;
            }
        }

        // Apply tag filter
        if (!empty($filters['tags'])) {
            $queryBuilder
                ->innerJoin('p.tags', 't')
                ->andWhere('t.slug IN (:tagSlugs)')
                ->setParameter('tagSlugs', $filters['tags']);
        }

        // Validate sort field to prevent SQL injection
        $allowedSortFields = [
            'id', 'name', 'price', 'createdAt', 'updatedAt'
        ];

        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'id'; // Default to ID if invalid sort field
        }

        // Add sorting
        $queryBuilder->orderBy('p.' . $sortBy, $sortOrder);

        return $queryBuilder;
    }
}
