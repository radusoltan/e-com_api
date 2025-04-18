<?php

namespace App\Controller;

use App\DTO\Response\ApiResponse;
use App\DTO\User\UserRegistrationDTO;
use App\Service\RefreshTokenService;
use App\Service\RequestValidatorService;
use App\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class RegistrationController extends AbstractController
{
    /**
     * Register a new user
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        RequestValidatorService $requestValidator,
        UserService $userService,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenService $refreshTokenService
    ): JsonResponse {
        try {
            // Deserialize and validate request into DTO
            /** @var UserRegistrationDTO $registrationDTO */
            $registrationDTO = $requestValidator->validateRequestToDto(
                $request,
                UserRegistrationDTO::class
            );

            // Register user
            $result = $userService->registerFromDTO($registrationDTO);

            if ($result['errors']) {
                return new JsonResponse(
                    ApiResponse::error('Registration failed', $result['errors'])->toArray(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Get user from result
            $user = $result['user'];

            // Create refresh token
            $refreshToken = $refreshTokenService->create($user, $request);

            // Generate JWT token
            $token = $jwtManager->create($user);

            // Return success response with tokens
            return new JsonResponse(
                ApiResponse::success([
                    'user' => [
                        'id' => $user->getId(),
                        'username' => $user->getUsername(),
                        'email' => $user->getEmail(),
                        'fullName' => $user->getFullName(),
                    ],
                    'token' => $token,
                    'refresh_token' => $refreshToken->getToken(),
                    'expires_at' => $refreshToken->getExpiresAt()->format('c'),
                ], 'User registered successfully')->toArray()
            );

        } catch (\Exception $e) {
            // This will be caught by our global exception handler
            throw $e;
        }
    }
}