<?php

namespace App\Controller;

use App\Service\RefreshTokenService;
use App\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api', name: 'api_')]
final class AuthController extends AbstractController
{
    /**
     * Refresh JWT token using a valid refresh token
     */
    #[Route('/token/refresh', name: 'token_refresh', methods: ['POST'])]
    public function refreshToken(
        Request $request,
        RefreshTokenService $refreshTokenService,
        JWTTokenManagerInterface $jwtManager,
        UserService $userService
    ): JsonResponse
    {
        // Validate content type
        if (!str_starts_with($request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid Content-Type. Expected application/json',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Parse and validate JSON payload
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid JSON format',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check for refresh token
        $refreshToken = $data['refresh_token'] ?? null;
        if (!$refreshToken) {
            return $this->json([
                'success' => false,
                'error' => 'Missing refresh_token parameter',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate refresh token
        $user = $refreshTokenService->validate($refreshToken, $request);
        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid or expired refresh token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Update user last login time
        $userService->updateLastLogin($user);

        // Implement refresh token rotation (delete old, create new)
        $refreshTokenService->delete($refreshToken);
        $newRefreshToken = $refreshTokenService->create($user, $request);

        // Return new JWT token and refresh token
        return $this->json([
            'success' => true,
            'token' => $jwtManager->create($user),
            'refresh_token' => $newRefreshToken->getToken(),
            'expires_at' => $newRefreshToken->getExpiresAt()->format('c'),
        ]);
    }

    /**
     * Logout endpoint - revokes the refresh token
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(
        Request $request,
        RefreshTokenService $refreshTokenService
    ): JsonResponse {
        // Validate content type
        if (!str_starts_with($request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid Content-Type. Expected application/json',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Parse and validate JSON payload
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid JSON format',
            ], Response::HTTP_BAD_REQUEST);
        }

        $refreshToken = $data['refresh_token'] ?? null;
        if ($refreshToken) {
            $refreshTokenService->delete($refreshToken);
        }

        return $this->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ], Response::HTTP_OK);
    }

    /**
     * Get current user details
     */
    #[Route('/me', name: 'current_user', methods: ['GET'])]
    public function currentUser(
        TokenStorageInterface $tokenStorage
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Not authenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'lastLogin' => $user->getLastLogin()?->format('c'),
            ],
        ]);
    }
}
