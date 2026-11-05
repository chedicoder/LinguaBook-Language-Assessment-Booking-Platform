<?php

namespace App\EventSubscriber;

use App\Exception\ApiException;
use App\Service\ApiResponder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiResponder $responder,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', -10]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        if ($exception instanceof ApiException) {
            $event->setResponse($this->responder->error(
                $exception->errorCode,
                $exception->getMessage(),
                $exception->status,
                $exception->details
            ));
            return;
        }

        if ($exception instanceof AuthenticationException) {
            $event->setResponse($this->responder->error('unauthorized', 'Authentication is required.', 401));
            return;
        }
        if ($exception instanceof AccessDeniedException) {
            $event->setResponse($this->responder->error('forbidden', 'You are not allowed to perform this action.', 403));
            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $code = match ($status) {
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'not_found',
                default => 'request_failed',
            };
            $message = match ($status) {
                401 => 'Authentication is required.',
                403 => 'You are not allowed to perform this action.',
                404 => 'The requested resource was not found.',
                default => 'The request could not be completed.',
            };
            $event->setResponse($this->responder->error($code, $message, $status));
            return;
        }

        $this->logger->error('Unhandled API exception.', [
            'exception' => $exception,
            'path' => $event->getRequest()->getPathInfo(),
        ]);
        $event->setResponse($this->responder->error(
            'internal_error',
            'An unexpected error occurred.',
            500
        ));
    }
}
