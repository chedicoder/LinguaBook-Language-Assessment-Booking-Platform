<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponder
{
    public function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return new JsonResponse(['data' => $data, 'meta' => (object) $meta, 'error' => null], $status);
    }

    public function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return new JsonResponse([
            'data' => null,
            'meta' => (object) [],
            'error' => ['code' => $code, 'message' => $message, 'details' => $details],
        ], $status);
    }
}
