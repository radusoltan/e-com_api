<?php

namespace App\Service;

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

    public function register(
        string $username,
        string $email,
        string $password,
        ?string $firstName,
        ?string $lastName,
        string $defaultRole = 'staff'
    )
    {
        // Check if User exists
        if($this->userRepository->findOneBy(['username' => $username])) return [
            'user' => null,
            'errors' => [
                'username' => "This username is already taken."
            ]
        ];

        // Check if email already exists
        if($this->userRepository->findOneBy(['email' => $email])) return [
            'user' => null,
            'errors' => [
                'email' => "This email is already taken."
            ]
        ];

        // Create new User
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsActive(true);

        if($firstName) $user->setFirstName($firstName);
        if($lastName) $user->setLastName($lastName);

        // Find default Role
        $role = $this->roleRepository->findByName($defaultRole);
        if($role) $user->addRole($role);

        $errors = $this->validator->validate($user);
        if(count($errors) > 0) {
            $errorMessages = [];
            foreach($errors as $error) {
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
        } catch (\Exception $e){
            return [
                'user' => null,
                'errors' => [
                    'system' => 'Failed to register user.' . $e->getMessage(),
                ]
            ];
        }
    }
}