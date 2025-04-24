<?php

namespace App\EventListener;

use App\Service\RateLimiterService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class GlobalApiRateLimitListener implements EventSubscriberInterface
{
    private const API_PATH_PREFIX = '/api';

    public function __construct(
        private RateLimiterService $rateLimiterService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10], // Higher priority than PreAuthenticationListener
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only apply to API routes
        if (!str_starts_with($request->getPathInfo(), self::API_PATH_PREFIX)) {
            return;
        }

        // Skip if the request is for a static resource
        if (in_array($request->getMethod(), ['OPTIONS', 'HEAD'])) {
            return;
        }

        // Apply global API rate limiting
        $limiterResponse = $this->rateLimiterService->check($request, 'global_api');
        if ($limiterResponse instanceof JsonResponse) {
            $event->setResponse($limiterResponse);
            $event->stopPropagation();
        }
    }

}