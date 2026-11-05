<?php

namespace App\Service;

use App\Exception\ApiException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CsrfService
{
    private const COOKIE_NAME = 'csrf_token';

    public function __construct(private readonly bool $cookieSecure)
    {
    }

    public function issue(Response $response): string
    {
        $token = bin2hex(random_bytes(32));
        $response->headers->setCookie(Cookie::create(self::COOKIE_NAME)
            ->withValue($token)
            ->withPath('/')
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(false)
            ->withSameSite(Cookie::SAMESITE_STRICT));

        return $token;
    }

    public function assertValid(Request $request): void
    {
        $cookie = $request->cookies->get(self::COOKIE_NAME);
        $header = $request->headers->get('X-CSRF-Token');
        if (!is_string($cookie) || !is_string($header) || $cookie === '' || !hash_equals($cookie, $header)) {
            throw new ApiException(403, 'csrf_invalid', 'CSRF token is missing or invalid.');
        }
    }

    public function clear(Response $response): void
    {
        $response->headers->clearCookie(self::COOKIE_NAME, '/', null, $this->cookieSecure, false, Cookie::SAMESITE_STRICT);
    }
}
