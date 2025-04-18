<?php

namespace App\Controller;

use App\DTO\Response\ApiResponse;
use App\Service\RefreshTokenService;
use App\Service\RequestValidatorService;
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
        RequestValidatorService $requestValidator,
        RefreshTokenService $refreshTokenService,
        JWTTokenManagerInterface $jwtManager,
        UserService $userService
    ): JsonResponse {
        try {
            $data = $requestValidator->parseJsonRequest($request);

            // Check for refresh token
            $refreshToken = $data['refresh_token'] ?? null;
            if (!$refreshToken) {
                return new JsonResponse(
                    ApiResponse::error('Missing refresh_token parameter')->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Validate refresh token
            $user = $refreshTokenService->validate($refreshToken, $request);
            if (!$user) {
                return new JsonResponse(
                    ApiResponse::error('Invalid or expired refresh token')->toArray(),
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Update user last login time
            $userService->updateLastLogin($user);

            // Implement refresh token rotation (delete old, create new)
            $refreshTokenService->delete($refreshToken);
            $newRefreshToken = $refreshTokenService->create($user, $request);

            // Return new JWT token and refresh token
            return new JsonResponse(
                ApiResponse::success([
                    'token' => $jwtManager->create($user),
                    'refresh_token' => $newRefreshToken->getToken(),
                    'expires_at' => $newRefreshToken->getExpiresAt()->format('c'),
                ], 'Token refreshed successfully')->toArray()
            );
        } catch (\Exception $e) {
            // This will be caught by our global exception handler, but we keep
            // this catch block for additional handling if needed
            throw $e;
        }
    }

    /**
     * Logout endpoint - revokes the refresh token
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(
        Request $request,
        RequestValidatorService $requestValidator,
        RefreshTokenService $refreshTokenService
    ): JsonResponse {
        try {
            $data = $requestValidator->parseJsonRequest($request);

            $refreshToken = $data['refresh_token'] ?? null;
            if ($refreshToken) {
                $refreshTokenService->delete($refreshToken);
            }

            return new JsonResponse(
                ApiResponse::success(null, 'Successfully logged out')->toArray(),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            // This will be caught by our global exception handler
            throw $e;
        }
    }

    /**
     * Get current user details
     */
    #[Route('/me', name: 'current_user', methods: ['GET'])]
    public function currentUser(): JsonResponse {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(
                ApiResponse::error('Not authenticated')->toArray(),
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new JsonResponse(
            ApiResponse::success([
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'lastLogin' => $user->getLastLogin()?->format('c'),
            ])->toArray()
        );
    }
}