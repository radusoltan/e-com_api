<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
class RefreshTokenService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function create(User $user): RefreshToken
    {
        $token = new RefreshToken();
        $token->setToken(Uuid::v4()->toRfc4122());
        $token->setUser($user);
        $token->setExpiresAt(new \DateTimeImmutable('+30 days'));

        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    public function validate(string $token): ?User
    {
        $repo = $this->em->getRepository(RefreshToken::class);
        $tokenEntity = $repo->findOneBy(['token' => $token]);

        if (!$tokenEntity || $tokenEntity->getExpiresAt() < new \DateTimeImmutable()) {
            return null;
        }

        return $tokenEntity->getUser();
    }

    public function delete(string $token): void
    {
        $entity = $this->em->getRepository(RefreshToken::class)->findOneBy(['token' => $token]);
        if ($entity) {
            $this->em->remove($entity);
            $this->em->flush();
        }
    }

}