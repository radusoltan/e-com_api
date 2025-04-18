<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimitListener implements EventSubscriberInterface
{
    // Inject the rate limiter factories
    public function __construct(
        private RateLimiterFactory $loginLimiter,
        private RateLimiterFactory $registerLimiter,
        private RateLimiterFactory $tokenRefreshLimiter,
        private RateLimiterFactory $apiLimiter
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10], // Higher priority to run early
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Skip rate limiting for non-main requests (like sub-requests)
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Check which limiter to apply based on the path
        $limiter = $this->getLimiterForRequest($request);
        if (!$limiter) {
            return;
        }

        // Use the client IP as the key for rate limiting
        // In production with a reverse proxy, you might need to adjust how you get the client IP
        $key = $request->getClientIp();

        // Try to consume a token from the limiter
        $limit = $limiter->consume($key);

        // Add rate limit headers to the response
        $response = $event->getResponse();
        if ($response === null) {
            // Create a response to attach headers if none exists
            $response = new JsonResponse();
            $event->setResponse($response);
        }

        $headers = $response->headers;
        $headers->set('X-RateLimit-Remaining', $limit->getRemainingTokens());
        $headers->set('X-RateLimit-Retry-After', $limit->getRetryAfter()->getTimestamp());
        $headers->set('X-RateLimit-Limit', $limit->getLimit());

        // If the limit is exceeded, return a 429 Too Many Requests response
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

            $response = new JsonResponse([
                'success' => false,
                'error' => 'Rate limit exceeded. Try again later.',
                'retry_after' => $retryAfter
            ], Response::HTTP_TOO_MANY_REQUESTS);

            $response->headers->set('Retry-After', $retryAfter);
            $event->setResponse($response);
        }
    }

    /**
     * Determine which rate limiter to use based on the request path
     */
    private function getLimiterForRequest(Request $request): ?RateLimiterFactory
    {
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Check for login endpoint
        if ($path === '/api/login_check' && $method === 'POST') {
            return $this->loginLimiter;
        }

        // Check for registration endpoint
        if ($path === '/api/register' && $method === 'POST') {
            return $this->registerLimiter;
        }

        // Check for token refresh endpoint
        if ($path === '/api/token/refresh' && $method === 'POST') {
            return $this->tokenRefreshLimiter;
        }

        // Apply general API rate limiting for all /api paths
        if (str_starts_with($path, '/api')) {
            return $this->apiLimiter;
        }

        return null;
    }
}