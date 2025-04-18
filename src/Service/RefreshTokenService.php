<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

class RefreshTokenService
{
    private const TOKEN_TTL_DAYS = 30;

    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Create a new refresh token for a user
     */
    public function create(User $user, ?Request $request = null): RefreshToken
    {
        // Generate a secure token using UUID + random bytes
        $tokenValue = Uuid::v4()->toRfc4122() . bin2hex(random_bytes(16));

        $token = new RefreshToken();
        $token->setToken($tokenValue);
        $token->setUser($user);
        $token->setExpiresAt(new \DateTimeImmutable('+' . self::TOKEN_TTL_DAYS . ' days'));

        // Store client information for security audit
        if ($request) {
            $token->setIpAddress($request->getClientIp());
            $token->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    /**
     * Validate a refresh token and return the associated user
     */
    public function validate(string $token, ?Request $request = null): ?User
    {
        $tokenEntity = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);

        if (!$tokenEntity || $tokenEntity->isExpired()) {
            return null;
        }

        // Enhanced security - optionally validate client information
        if ($request && $tokenEntity->getIpAddress()) {
            $clientIp = $request->getClientIp();

            // Basic check - could be replaced with more sophisticated IP validation
            if ($tokenEntity->getIpAddress() !== $clientIp) {
                // Consider logging this security event
                return null;
            }
        }

        return $tokenEntity->getUser();
    }

    /**
     * Delete a specific refresh token
     */
    public function delete(string $token): void
    {
        $tokenEntity = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);

        if ($tokenEntity) {
            $this->em->remove($tokenEntity);
            $this->em->flush();
        }
    }

    /**
     * Delete all refresh tokens for a user
     */
    public function deleteAllForUser(User $user): void
    {
        $tokens = $this->em->getRepository(RefreshToken::class)
            ->findBy(['user' => $user]);

        foreach ($tokens as $token) {
            $this->em->remove($token);
        }

        $this->em->flush();
    }

    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $now = new \DateTimeImmutable();
        $count = 0;

        $expiredTokens = $this->em->getRepository(RefreshToken::class)
            ->createQueryBuilder('rt')
            ->where('rt.expiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($expiredTokens as $token) {
            $this->em->remove($token);
            $count++;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }
}