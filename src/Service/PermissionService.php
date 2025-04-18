<?php

namespace App\Service;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PermissionService
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository,
        private Security $security
    ){}

    /**
     * Check if the current user has a specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Use Symfony's built-in role hierarchy - if user is admin they have all permissions
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // For manager, grant specific permission sets
        if ($this->security->isGranted('ROLE_MANAGER')) {
            $managerPermissions = ['user_view', 'product_view', 'product_create', 'product_edit',
                'order_view', 'inventory_view', /* etc. */];
            if (in_array($permissionName, $managerPermissions)) {
                return true;
            }
        }

        // Fall back to direct permission check
        return $user->hasPermission($permissionName);
    }

    /**
     * Create a new role with the given permissions
     */
    public function createRole(string $name, string $description, array $permissionIds = []): Role
    {
        $role = new Role();
        $role->setName($name);
        $role->setDescription($description);

        if (!empty($permissionIds)) {
            $permissions = $this->permissionRepository->findByIds($permissionIds);

            foreach ($permissions as $permission) {
                $role->addPermission($permission);
            }
        }

        $this->entityManager->persist($role);
        $this->entityManager->flush();

        return $role;
    }

    /**
     * Update an existing role with new permissions
     */
    public function updateRole(Role $role, string $name, string $description, array $permissionIds = []): Role
    {
        $role->setName($name);
        $role->setDescription($description);

        // Remove all existing permissions
        foreach ($role->getPermissions()->toArray() as $permission) {
            $role->removePermission($permission);
        }

        // Add new permissions
        if (!empty($permissionIds)) {
            $permissions = $this->permissionRepository->findByIds($permissionIds);

            foreach ($permissions as $permission) {
                $role->addPermission($permission);
            }
        }

        $this->entityManager->flush();

        return $role;
    }

    /**
     * Create a new permission
     */
    public function createPermission(string $name, string $description, string $category): Permission
    {
        $permission = new Permission();
        $permission->setName($name);
        $permission->setDescription($description);
        $permission->setCategory($category);

        $this->entityManager->persist($permission);
        $this->entityManager->flush();

        return $permission;
    }

    /**
     * Initialize default roles and permissions
     */
    public function initializeDefaultRolesAndPermissions(): void
    {
        // Create default categories and permissions if they don't exist
        $this->createDefaultPermissions();

        // Create default roles if they don't exist
        $this->createDefaultRoles();
    }

    /**
     * Create default permissions
     */
    private function createDefaultPermissions(): void
    {
        $defaultPermissions = [
            // User management permissions
            ['name' => 'user_view', 'category' => 'user', 'description' => 'View users'],
            ['name' => 'user_create', 'category' => 'user', 'description' => 'Create users'],
            ['name' => 'user_edit', 'category' => 'user', 'description' => 'Edit users'],
            ['name' => 'user_delete', 'category' => 'user', 'description' => 'Delete users'],

            // Role management permissions
            ['name' => 'role_view', 'category' => 'role', 'description' => 'View roles'],
            ['name' => 'role_create', 'category' => 'role', 'description' => 'Create roles'],
            ['name' => 'role_edit', 'category' => 'role', 'description' => 'Edit roles'],
            ['name' => 'role_delete', 'category' => 'role', 'description' => 'Delete roles'],

            // Permission management
            ['name' => 'permission_view', 'category' => 'permission', 'description' => 'View permissions'],
            ['name' => 'permission_create', 'category' => 'permission', 'description' => 'Create permissions'],
            ['name' => 'permission_edit', 'category' => 'permission', 'description' => 'Edit permissions'],
            ['name' => 'permission_delete', 'category' => 'permission', 'description' => 'Delete permissions'],

            // Product management permissions
            ['name' => 'product_view', 'category' => 'product', 'description' => 'View products'],
            ['name' => 'product_create', 'category' => 'product', 'description' => 'Create products'],
            ['name' => 'product_edit', 'category' => 'product', 'description' => 'Edit products'],
            ['name' => 'product_delete', 'category' => 'product', 'description' => 'Delete products'],

            // Order management permissions
            ['name' => 'order_view', 'category' => 'order', 'description' => 'View orders'],
            ['name' => 'order_create', 'category' => 'order', 'description' => 'Create orders'],
            ['name' => 'order_edit', 'category' => 'order', 'description' => 'Edit orders'],
            ['name' => 'order_delete', 'category' => 'order', 'description' => 'Delete orders'],

            // Inventory management permissions
            ['name' => 'inventory_view', 'category' => 'inventory', 'description' => 'View inventory'],
            ['name' => 'inventory_update', 'category' => 'inventory', 'description' => 'Update inventory'],

            // System configuration permissions
            ['name' => 'config_view', 'category' => 'system', 'description' => 'View system configuration'],
            ['name' => 'config_edit', 'category' => 'system', 'description' => 'Edit system configuration'],
        ];

        foreach ($defaultPermissions as $permissionData) {
            $existingPermission = $this->permissionRepository->findByName($permissionData['name']);

            if (!$existingPermission) {
                $permission = new Permission();
                $permission->setName($permissionData['name']);
                $permission->setCategory($permissionData['category']);
                $permission->setDescription($permissionData['description']);

                $this->entityManager->persist($permission);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Create default roles
     */
    private function createDefaultRoles(): void
    {
        // Admin role with all permissions
        $adminRole = $this->roleRepository->findByName('admin');
        if (!$adminRole) {
            $adminRole = new Role();
            $adminRole->setName('admin');
            $adminRole->setDescription('Administrator with all permissions');

            $allPermissions = $this->permissionRepository->findAll();
            foreach ($allPermissions as $permission) {
                $adminRole->addPermission($permission);
            }

            $this->entityManager->persist($adminRole);
        }

        // Manager role with limited permissions
        $managerRole = $this->roleRepository->findByName('manager');
        if (!$managerRole) {
            $managerRole = new Role();
            $managerRole->setName('manager');
            $managerRole->setDescription('Manager with limited administrative permissions');

            $managerPermissions = [
                'user_view', 'product_view', 'product_create', 'product_edit',
                'order_view', 'order_create', 'order_edit',
                'inventory_view', 'inventory_update',
            ];

            foreach ($managerPermissions as $permissionName) {
                $permission = $this->permissionRepository->findByName($permissionName);
                if ($permission) {
                    $managerRole->addPermission($permission);
                }
            }

            $this->entityManager->persist($managerRole);
        }

        // Staff role with basic permissions
        $staffRole = $this->roleRepository->findByName('staff');
        if (!$staffRole) {
            $staffRole = new Role();
            $staffRole->setName('staff');
            $staffRole->setDescription('Staff with basic permissions');

            $staffPermissions = [
                'product_view', 'order_view', 'inventory_view'
            ];

            foreach ($staffPermissions as $permissionName) {
                $permission = $this->permissionRepository->findByName($permissionName);
                if ($permission) {
                    $staffRole->addPermission($permission);
                }
            }

            $this->entityManager->persist($staffRole);
        }

        $this->entityManager->flush();
    }

}