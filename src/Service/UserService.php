<?php

namespace App\Service;

use App\DTO\User\UserRegistrationDTO;
use App\Entity\User;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private RoleRepository $roleRepository,
        private UserRepository $userRepository,
        private ValidatorInterface $validator
    ){}

    /**
     * Register a new user
     *
     * @param UserRegistrationDTO $dto
     * @param string $defaultRole
     * @return array{user: ?User, errors: ?array<string, string>}
     */
    public function registerFromDTO(UserRegistrationDTO $dto, string $defaultRole = 'staff'): array
    {
        return $this->register(
            $dto->getUsername(),
            $dto->getEmail(),
            $dto->getPassword(),
            $dto->getFirstName(),
            $dto->getLastName(),
            $defaultRole
        );
    }

    /**
     * Register a new user
     *
     * @param string $username
     * @param string $email
     * @param string $password
     * @param string|null $firstName
     * @param string|null $lastName
     * @param string $defaultRole
     * @return array{user: ?User, errors: ?array<string, string>}
     */
    public function register(
        string $username,
        string $email,
        string $password,
        ?string $firstName = null,
        ?string $lastName = null,
        string $defaultRole = 'staff'
    ): array
    {
        // Check if user exists by username
        if ($this->userRepository->findOneBy(['username' => $username])) {
            return [
                'user' => null,
                'errors' => [
                    'username' => "This username is already taken."
                ]
            ];
        }

        // Check if email already exists
        if ($this->userRepository->findOneBy(['email' => $email])) {
            return [
                'user' => null,
                'errors' => [
                    'email' => "This email is already taken."
                ]
            ];
        }

        // Create new User
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsActive(true);

        if ($firstName) {
            $user->setFirstName($firstName);
        }

        if ($lastName) {
            $user->setLastName($lastName);
        }

        // Find default Role
        $role = $this->roleRepository->findByName($defaultRole);
        if ($role) {
            $user->addRole($role);
        } else {
            // Log warning about missing role
            // Consider whether to fail the registration or continue
        }

        // Validate the user entity
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return [
                'user' => null,
                'errors' => $errorMessages
            ];
        }

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return [
                'user' => $user,
                'errors' => null
            ];
        } catch (\Exception $e) {
            return [
                'user' => null,
                'errors' => [
                    'system' => 'Failed to register user: ' . $e->getMessage(),
                ]
            ];
        }
    }

    /**
     * Update user's last login time
     */
    public function updateLastLogin(User $user): void
    {
        $user->updateLastLogin();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Find user by ID
     */
    public function findById(int $id): ?User
    {
        return $this->userRepository->find($id);
    }

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?User
    {
        return $this->userRepository->findOneBy(['username' => $username]);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->userRepository->findOneBy(['email' => $email]);
    }

    /**
     * Change user password
     *
     * @return array{success: bool, errors: ?array<string, string>}
     */
    public function changePassword(User $user, string $newPassword): array
    {
        try {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return [
                'success' => true,
                'errors' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => [
                    'system' => 'Failed to change password: ' . $e->getMessage()
                ]
            ];
        }
    }
}