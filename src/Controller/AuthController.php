<?php

namespace App\Controller;

use App\DTO\Response\ApiResponse;
use App\Service\JwtBlacklistService;
use App\Service\RateLimiterService;
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
    public function refresh(
        Request $request,
        RequestValidatorService $requestValidator,
        RefreshTokenService $refreshTokenService,
        JWTTokenManagerInterface $jwtManager,
        UserService $userService,
        RateLimiterService $rateLimiterService
    ): JsonResponse {
        // Apply rate limiting
        $limiterResponse = $rateLimiterService->check($request, 'token_refresh');
        if ($limiterResponse instanceof JsonResponse) {
            return $limiterResponse;
        }

        try {
            // Validate request format
            $data = $requestValidator->parseJsonRequest($request);

            // Check if refresh token is provided
            $refreshToken = $data['refresh_token'] ?? null;
            if (!$refreshToken) {
                return new JsonResponse(
                    ApiResponse::error('Missing refresh token parameter')->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Validate the refresh token and get the associated user
            $user = $refreshTokenService->validate($refreshToken, $request);
            if (!$user) {
                return new JsonResponse(
                    ApiResponse::error('Invalid or expired refresh token')->toArray(),
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Update user's last login timestamp
            $userService->updateLastLogin($user);

            // Create new tokens (token rotation for security)
            $refreshTokenService->delete($refreshToken);
            $newRefreshToken = $refreshTokenService->create($user, $request);
            $newJwtToken = $jwtManager->create($user);

            // Return successful response with new tokens
            return new JsonResponse(
                ApiResponse::success([
                    'token' => $newJwtToken,
                    'refresh_token' => $newRefreshToken->getToken(),
                    'expires_at' => $newRefreshToken->getExpiresAt()->format('c'),
                    'user' => [
                        'id' => $user->getId(),
                        'username' => $user->getUsername(),
                        'email' => $user->getEmail(),
                        'fullName' => $user->getFullName(),
                    ],
                ], 'Token refreshed successfully')->toArray()
            );

        } catch (\Exception $e) {
            // Return a generic error message to avoid information leakage
            // This will be caught by the global exception handler
            throw $e;
        }
    }

    /**
     * Logout endpoint - revokes the refresh token
     */
    #[Route('/logout', name: 'logout', methods: ['POST', 'OPTIONS'])]
    public function logout(
        Request $request,
        RequestValidatorService $requestValidator,
        RefreshTokenService $refreshTokenService,
        JwtBlacklistService $jwtBlacklistService,
        TokenStorageInterface $tokenStorage
    ): JsonResponse {
        try {
            $data = $requestValidator->parseJsonRequest($request);

            // Get JWT token from authorization header
            $token = $request->headers->get('Authorization');
            if ($token && strpos($token, 'Bearer ') === 0) {
                $token = substr($token, 7);
                // Add token to blacklist with TTL
                $jwtBlacklistService->blacklist($token, 3600); // 1 hour or match token TTL
            }

            $refreshToken = $data['refresh_token'] ?? null;
            if ($refreshToken) {
                $refreshTokenService->delete($refreshToken);
            }

            return new JsonResponse(
                ApiResponse::success(null, 'Successfully logged out')->toArray(),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
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