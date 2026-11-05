<?php

namespace App\EventSubscriber;

use App\Service\CsrfService;
use App\Exception\ApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $frontendOrigin,
        private readonly CsrfService $csrf
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 100],
            KernelEvents::RESPONSE => ['onResponse', 100],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if ($request->getMethod() === 'OPTIONS') {
            $event->setResponse(new Response('', Response::HTTP_NO_CONTENT));
            return;
        }

        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $request->getPathInfo() !== '/api/auth/csrf') {
            $origin = $request->headers->get('Origin');
            if ($origin !== null && $origin !== $this->frontendOrigin) {
                throw new ApiException(403, 'origin_forbidden', 'This request origin is not allowed.');
            }
            $this->csrf->assertValid($request);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $response = $event->getResponse();
        $origin = $request->headers->get('Origin');
        if ($origin === $this->frontendOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $this->frontendOrigin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Vary', 'Origin');
        }
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-CSRF-Token');
        $response->headers->set('Access-Control-Max-Age', '600');
    }
}
