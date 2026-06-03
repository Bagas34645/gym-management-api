<?php

namespace App\Support;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', ?array $meta = null, int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(
        string $message,
        ?ErrorCode $errorCode = null,
        array $errors = [],
        int $status = 400,
        ?array $data = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode->value;
        }

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    public static function paginated(mixed $data, int $page, int $perPage, int $total, string $message = 'OK'): JsonResponse
    {
        return self::success($data, $message, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }
}
