<?php

namespace App\Service;

use App\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;

final class JsonBody
{
    public function decode(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'invalid_json', 'Request body must be valid JSON.');
        }

        if (!is_array($data)) {
            throw new ApiException(400, 'invalid_json', 'Request body must be a JSON object.');
        }

        return $data;
    }
}
