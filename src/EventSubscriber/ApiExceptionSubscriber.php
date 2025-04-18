<?php

namespace App\EventSubscriber;

use App\DTO\Response\ApiResponse;
use App\Exception\InvalidRequestException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private const API_PATH_PREFIX = '/api';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Only handle exceptions for API routes
        if (!str_starts_with($request->getPathInfo(), self::API_PATH_PREFIX)) {
            return;
        }

        $exception = $event->getThrowable();
        $response = $this->createApiResponse($exception);

        $event->setResponse($response);
    }

    private function createApiResponse(\Throwable $exception): JsonResponse
    {
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $apiResponse = null;

        if ($exception instanceof InvalidRequestException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $apiResponse = ApiResponse::error($exception->getMessage(), $exception->getErrors());
        } elseif ($exception instanceof NotFoundHttpException) {
            $statusCode = Response::HTTP_NOT_FOUND;
            $apiResponse = ApiResponse::error('Resource not found');
        } elseif ($exception instanceof AccessDeniedHttpException) {
            $statusCode = Response::HTTP_FORBIDDEN;
            $apiResponse = ApiResponse::error('Access denied');
        } elseif ($exception instanceof AuthenticationException) {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $apiResponse = ApiResponse::error('Authentication required');
        } elseif ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $apiResponse = ApiResponse::error($exception->getMessage());
        } else {
            // For production, you might want to hide the actual error message
            $message = $_ENV['APP_ENV'] === 'dev' ? $exception->getMessage() : 'An unexpected error occurred';
            $apiResponse = ApiResponse::error($message);

            // Log the real error for unexpected exceptions
            error_log($exception->getMessage() . "\n" . $exception->getTraceAsString());
        }

        return new JsonResponse(
            $apiResponse->toArray(),
            $statusCode
        );
    }
}