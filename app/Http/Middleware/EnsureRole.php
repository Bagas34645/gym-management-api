<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthorized', null, [], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return ApiResponse::error('Tidak memiliki hak akses', null, [], 403);
        }

        return $next($request);
    }
}
