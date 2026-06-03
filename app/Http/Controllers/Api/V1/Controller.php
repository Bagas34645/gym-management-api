<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

abstract class Controller extends BaseController
{
    protected function success(mixed $data = null, string $message = 'OK', ?array $meta = null, int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $meta, $status);
    }

    protected function paginated($paginator, string $message = 'OK'): JsonResponse
    {
        return ApiResponse::paginated(
            $paginator->items(),
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
            $message,
        );
    }
}
