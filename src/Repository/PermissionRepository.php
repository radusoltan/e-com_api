<?php

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /**
     * Find a permission by its name
     */
    public function findByName(string $name): ?Permission
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Find all permissions grouped by category
     *
     * @return array<string, Permission[]>
     */
    public function findAllGroupedByCategory(): array
    {
        $permissions = $this->createQueryBuilder('p')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($permissions as $permission) {
            $category = $permission->getCategory();
            if (!isset($result[$category])) {
                $result[$category] = [];
            }
            $result[$category][] = $permission;
        }

        return $result;
    }

    /**
     * Find permissions by multiple IDs
     *
     * @param array<int> $ids
     * @return Permission[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find permissions by category
     */
    public function findByCategory(string $category): array
    {
        return $this->findBy(['category' => $category], ['name' => 'ASC']);
    }
}
