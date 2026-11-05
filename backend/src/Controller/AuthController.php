<?php

namespace App\Controller;

use App\DTO\LoginRequest;
use App\DTO\RegisterRequest;
use App\Exception\ApiException;
use App\Service\ApiResponder;
use App\Service\ApiView;
use App\Service\AuthService;
use App\Service\CsrfService;
use App\Service\DtoValidator;
use App\Service\JsonBody;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly JsonBody $jsonBody,
        private readonly ApiResponder $responder,
        private readonly ApiView $view,
        private readonly AuthService $auth,
        private readonly DtoValidator $validator,
        private readonly CsrfService $csrf,
        private readonly JWTTokenManagerInterface $jwt,
        private readonly RateLimiterFactory $authLimiter,
        private readonly int $jwtTtl,
        private readonly bool $cookieSecure
    ) {
    }

    #[Route('/csrf', name: 'auth_csrf', methods: ['GET'])]
    public function csrf(): JsonResponse
    {
        $response = $this->responder->success(['csrfToken' => null]);
        $token = $this->csrf->issue($response);
        $response->setData(['data' => ['csrfToken' => $token], 'meta' => (object) [], 'error' => null]);
        return $response;
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $this->limit($request);
        $data = $this->jsonBody->decode($request);
        $this->assertFields($data, ['name', 'email', 'password']);
        foreach (['name', 'email', 'password'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                throw new ApiException(422, 'validation_failed', 'The submitted data is invalid.');
            }
        }

        $user = $this->auth->register(new RegisterRequest($data['name'], $data['email'], $data['password']));
        return $this->responder->success(['user' => $this->view->user($user)], 201);
    }

    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $this->limit($request);
        $data = $this->jsonBody->decode($request);
        $this->assertFields($data, ['email', 'password']);
        if (!isset($data['email'], $data['password']) || !is_string($data['email']) || !is_string($data['password'])) {
            throw new ApiException(422, 'validation_failed', 'The submitted data is invalid.');
        }

        $input = new LoginRequest($data['email'], $data['password']);
        $this->validator->validate($input);
        $user = $this->auth->verifyCredentials($input->email, $input->password);
        $response = $this->responder->success(['user' => $this->view->user($user), 'csrfToken' => null]);
        $csrfToken = $this->csrf->issue($response);
        $response->setData([
            'data' => ['user' => $this->view->user($user), 'csrfToken' => $csrfToken],
            'meta' => (object) [],
            'error' => null,
        ]);
        $response->headers->setCookie(Cookie::create('auth_token')
            ->withValue($this->jwt->create($user))
            ->withExpires(new \DateTimeImmutable(sprintf('+%d seconds', $this->jwtTtl)))
            ->withPath('/')
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));
        return $response;
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Document\User) {
            throw new ApiException(401, 'unauthorized', 'Authentication is required.');
        }

        return $this->responder->success(['user' => $this->view->user($user)]);
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = $this->responder->success(['loggedOut' => true, 'csrfToken' => null]);
        $response->headers->clearCookie('auth_token', '/', null, $this->cookieSecure, true, Cookie::SAMESITE_LAX);
        $csrfToken = $this->csrf->issue($response);
        $response->setData([
            'data' => ['loggedOut' => true, 'csrfToken' => $csrfToken],
            'meta' => (object) [],
            'error' => null,
        ]);
        return $response;
    }

    private function limit(Request $request): void
    {
        $limit = $this->authLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new ApiException(429, 'rate_limited', 'Too many authentication attempts. Try again later.');
        }
    }

    private function assertFields(array $data, array $allowed): void
    {
        if (array_diff(array_keys($data), $allowed) !== []) {
            throw new ApiException(422, 'unknown_fields', 'The request contains unsupported fields.');
        }
    }
}
