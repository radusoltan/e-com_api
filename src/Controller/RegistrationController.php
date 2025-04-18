<?php

namespace App\Controller;

use App\DTO\User\UserRegistrationDTO;
use App\Service\RefreshTokenService;
use App\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
final class RegistrationController extends AbstractController
{
    /**
     * Register a new user
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserService $userService,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenService $refreshTokenService
    ): JsonResponse {
        // Validate content type
        if (!str_starts_with($request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid Content-Type. Expected application/json',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Deserialize request into DTO
            /** @var UserRegistrationDTO $registrationDTO */
            $registrationDTO = $serializer->deserialize(
                $request->getContent(),
                UserRegistrationDTO::class,
                'json'
            );

            // Validate DTO
            $errors = $validator->validate($registrationDTO);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }

                return $this->json([
                    'success' => false,
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Register user
            $result = $userService->registerFromDTO($registrationDTO);

            if ($result['errors']) {
                return $this->json([
                    'success' => false,
                    'errors' => $result['errors'],
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get user from result
            $user = $result['user'];

            // Create refresh token
            $refreshToken = $refreshTokenService->create($user, $request);

            // Generate JWT token
            $token = $jwtManager->create($user);

            // Return success response with tokens
            return $this->json([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'fullName' => $user->getFullName(),
                ],
                'token' => $token,
                'refresh_token' => $refreshToken->getToken(),
                'expires_at' => $refreshToken->getExpiresAt()->format('c'),
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Registration failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
