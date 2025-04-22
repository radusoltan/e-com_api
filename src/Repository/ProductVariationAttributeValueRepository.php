<?php

namespace App\Repository;

use App\Entity\ProductVariationAttributeValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVariationAttributeValue>
 */
class ProductVariationAttributeValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariationAttributeValue::class);
    }

    /**
     * Find attribute values for a specific variation
     *
     * @param int $variationId The variation ID
     * @return ProductVariationAttributeValue[]
     */
    public function findByVariation(int $variationId): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.variation = :variationId')
            ->setParameter('variationId', $variationId)
            ->innerJoin('v.attribute', 'a')
            ->addSelect('a')
            ->leftJoin('v.option', 'o')
            ->addSelect('o')
            ->orderBy('a.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attribute values for a specific variation and attribute
     *
     * @param int $variationId The variation ID
     * @param int $attributeId The attribute ID
     * @return ProductVariationAttributeValue|null
     */
    public function findOneByVariationAndAttribute(int $variationId, int $attributeId): ?ProductVariationAttributeValue
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.variation = :variationId')
            ->andWhere('v.attribute = :attributeId')
            ->setParameter('variationId', $variationId)
            ->setParameter('attributeId', $attributeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find variations with specific attribute option
     *
     * @param int $attributeId The attribute ID
     * @param int $optionId The option ID
     * @return ProductVariationAttributeValue[]
     */
    public function findByAttributeOption(int $attributeId, int $optionId): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.attribute = :attributeId')
            ->andWhere('v.option = :optionId')
            ->setParameter('attributeId', $attributeId)
            ->setParameter('optionId', $optionId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find variations by multiple attribute options
     * This is useful for filter variations that match specific configuration options
     *
     * @param array $attributeOptionMap Array mapping attribute IDs to option IDs
     * @return array Array of variation IDs that match all the criteria
     */
    public function findVariationIdsByAttributeOptions(array $attributeOptionMap): array
    {
        if (empty($attributeOptionMap)) {
            return [];
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $counter = 0;

        foreach ($attributeOptionMap as $attributeId => $optionId) {
            $alias = 'v' . $counter;

            $subQuery = $this->getEntityManager()->createQueryBuilder()
                ->select($alias . '.variation')
                ->from('App\Entity\ProductVariationAttributeValue', $alias)
                ->where($alias . '.attribute = :attr' . $counter)
                ->andWhere($alias . '.option = :opt' . $counter)
                ->setParameter('attr' . $counter, $attributeId)
                ->setParameter('opt' . $counter, $optionId);

            if ($counter === 0) {
                $qb->select('IDENTITY(' . $alias . '.variation)')
                    ->from('App\Entity\ProductVariationAttributeValue', $alias)
                    ->where($alias . '.attribute = :attr' . $counter)
                    ->andWhere($alias . '.option = :opt' . $counter)
                    ->setParameter('attr' . $counter, $attributeId)
                    ->setParameter('opt' . $counter, $optionId);
            } else {
                $qb->andWhere($qb->expr()->in('IDENTITY(' . $alias . '.variation)', $subQuery->getDQL()))
                    ->setParameter('attr' . $counter, $attributeId)
                    ->setParameter('opt' . $counter, $optionId);
            }

            $counter++;
        }

        return $qb->getQuery()->getResult();
    }
}
