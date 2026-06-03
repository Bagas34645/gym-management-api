<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AddRateLimitHeaders;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\JwtAuthenticate;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'auth.jwt' => JwtAuthenticate::class,
            'role' => EnsureRole::class,
            'rate.headers' => AddRateLimitHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    $e->userMessage,
                    $e->errorCode,
                    $e->errors,
                    $e->httpStatus,
                    $e->data,
                );
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    collect($e->errors())->flatten()->first() ?? 'Validasi gagal',
                    null,
                    $e->errors(),
                    422,
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return ApiResponse::error('Unauthorized', \App\Enums\ErrorCode::AuthInvalidToken, [], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return ApiResponse::error('Resource tidak ditemukan', null, [], 404);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return ApiResponse::error($e->getMessage() ?: 'Error', null, [], $e->getStatusCode());
            }
        });
    })->create();
