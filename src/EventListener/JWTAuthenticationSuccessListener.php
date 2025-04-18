<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\RefreshTokenService;
use App\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTAuthenticationSuccessListener
{

    public function __construct(
        private RefreshTokenService $refreshTokenService,
        private UserService $userService,
        private RequestStack $requestStack,
    ){}

    /**
     * Add refresh token to JWT authentication response and update user last login
     */
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void{
        $user = $event->getUser();

        // Only process User entities
        if(!$user instanceof User) return;

        // Update user`s last login timestamp
        $this->userService->updateLastLogin($user);

        // Generate a refresh token
        $refreshToken = $this->refreshTokenService->create(
            $user,
            $this->requestStack->getCurrentRequest(),
        );

        $data = $event->getData();
        $data['refresh_token'] = $refreshToken->getToken();
        $data['expires_at'] = $refreshToken->getExpiresAt()->format('c');

        $event->setData($data);
    }

}