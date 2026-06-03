<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddRateLimitHeaders
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->resolveKey($request);
        $cacheKey = 'rate_limit:'.$key.':'.$maxAttempts.':'.$decayMinutes;

        $data = cache()->get($cacheKey, ['count' => 0, 'reset' => now()->addMinutes($decayMinutes)->timestamp]);
        $data['count'] = ($data['count'] ?? 0) + 1;
        $remaining = max(0, $maxAttempts - $data['count']);

        if ($data['count'] === 1) {
            $data['reset'] = now()->addMinutes($decayMinutes)->timestamp;
        }

        cache()->put($cacheKey, $data, now()->addMinutes($decayMinutes));

        if ($data['count'] > $maxAttempts) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak request',
            ], 429)->withHeaders($this->headers($maxAttempts, 0, $data['reset']));
        }

        $response = $next($request);

        foreach ($this->headers($maxAttempts, $remaining, $data['reset']) as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    private function resolveKey(Request $request): string
    {
        $user = $request->user();

        if ($user) {
            return 'user:'.$user->id;
        }

        return 'ip:'.$request->ip();
    }

    private function headers(int $limit, int $remaining, int $reset): array
    {
        return [
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset' => (string) $reset,
        ];
    }
}
