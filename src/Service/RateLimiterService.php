<?php

namespace App\Service;

use App\DTO\Response\ApiResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
class RateLimiterService
{
    public function __construct(
        #[Autowire('@limiter.login')]
        private RateLimiterFactory $loginLimiter,
        #[Autowire('@limiter.registration')]
        private RateLimiterFactory $registrationLimiter,
        #[Autowire('@limiter.token_refresh')]
        private RateLimiterFactory $tokenRefreshLimiter,
        #[Autowire('@limiter.global_api')]
        private RateLimiterFactory $globalApiLimiter,
    ) {}

    /**
     * Check if the request is rate limited
     *
     * @param Request $request The request to check
     * @param string $limiterName The name of the limiter to use
     * @param string|null $key Optional key to use instead of the IP address
     * @return bool|JsonResponse Returns true if allowed, or a JsonResponse if rate limited
     */
    public function check(Request $request, string $limiterName, ?string $key = null): bool|JsonResponse
    {
        $limiter = match ($limiterName) {
            'login' => $this->loginLimiter,
            'registration' => $this->registrationLimiter,
            'token_refresh' => $this->tokenRefreshLimiter,
            'global_api' => $this->globalApiLimiter,
            default => throw new \InvalidArgumentException("Unknown rate limiter: $limiterName"),
        };

        // Use the provided key or fall back to the client IP
        $limiterKey = $key ?? $request->getClientIp();

        // Create the limiter for this specific key
        $rateLimiter = $limiter->create($limiterKey);

        // Try to consume a token
        $limiterResponse = $rateLimiter->consume();

        // Check if the limit is reached
        if (!$limiterResponse->isAccepted()) {
            $waitDuration = $limiterResponse->getRetryAfter();

            return new JsonResponse(
                ApiResponse::error('Too many attempts, please try again later.')->toArray(),
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Retry-After' => $waitDuration->getTimestamp() - time(),
                    'X-RateLimit-Remaining' => $limiterResponse->getRemainingTokens(),
                    'X-RateLimit-Reset' => $waitDuration->getTimestamp(),
                ]
            );
        }

        return true;
    }
}