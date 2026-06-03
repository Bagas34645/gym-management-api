<?php

namespace App\Http\Middleware;

use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return \App\Support\ApiResponse::error(
                'Token tidak valid atau sudah kadaluarsa',
                \App\Enums\ErrorCode::AuthInvalidToken,
                [],
                401,
            );
        }

        $token = trim(substr($header, 7));
        $user = $this->jwt->parseAccessToken($token);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
