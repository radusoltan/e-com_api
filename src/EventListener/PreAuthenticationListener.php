<?php

namespace App\EventListener;

use App\DTO\Response\ApiResponse;
use App\Service\RateLimiterService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PreAuthenticationListener implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterService $rateLimiterService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9], // Run before authentication
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Check if this is the login endpoint
        if ($request->getPathInfo() === '/api/login_check' && $request->isMethod('POST')) {
            $limiterResponse = $this->rateLimiterService->check($request, 'login');

            if ($limiterResponse instanceof JsonResponse) {
                $event->setResponse($limiterResponse);
                $event->stopPropagation();
            }
        }
    }
}