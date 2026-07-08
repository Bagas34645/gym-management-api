<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKioskApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('gym.kiosk_api_key');

        if (empty($configured)) {
            abort(503, 'Kiosk API key is not configured');
        }

        $provided = $request->header('X-Kiosk-Api-Key');

        if (! is_string($provided) || ! hash_equals($configured, $provided)) {
            abort(401, 'Invalid kiosk API key');
        }

        return $next($request);
    }
}
