<?php

namespace App\Controller;

use App\Service\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class AuthController extends AbstractController
{
    #[Route('/token/refresh', name: 'token_refresh', methods: ['POST'])]
    public function register(
        Request $request,
        RefreshTokenService $refreshTokenService,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse
    {
        if(!str_starts_with($request->headers->get('Content-Type'), 'application/json')) return $this->json([
            'success' => false,
            'error' => ['Invalid Content-Type'],
        ], Response::HTTP_BAD_REQUEST);

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $refreshToken = $data['refresh_token'] ?? null;

        if (!$refreshToken || !$user = $refreshTokenService->validate($refreshToken)) {
            return new JsonResponse(['error' => 'Invalid refresh token'], 401);
        }

        // Optional: implement refresh token rotation
        $refreshTokenService->delete($refreshToken);
        $newRefreshToken = $refreshTokenService->create($user);

        return new JsonResponse([
            'token' => $jwtManager->create($user),
            'refresh_token' => $newRefreshToken->getToken(),
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(
        Request $request,
        RefreshTokenService $refreshTokenService
    ): JsonResponse {
        if (!str_starts_with($request->headers->get('Content-Type'), 'application/json')) {
            return new JsonResponse(['error' => 'Invalid Content-Type'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $refreshToken = $data['refresh_token'] ?? null;

        if ($refreshToken) {
            $refreshTokenService->delete($refreshToken);
        }

        return new JsonResponse(null, 204);
    }
}
