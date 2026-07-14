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
        $now = now()->timestamp;

        $data = cache()->get($cacheKey);

        // Start a fresh window when missing or expired (do not slide TTL on every hit).
        if (! is_array($data)
            || ! isset($data['count'], $data['reset'])
            || $now >= (int) $data['reset']
        ) {
            $data = [
                'count' => 0,
                'reset' => now()->addMinutes($decayMinutes)->timestamp,
            ];
        }

        $data['count'] = (int) $data['count'] + 1;
        $remaining = max(0, $maxAttempts - $data['count']);
        $ttlSeconds = max(1, (int) $data['reset'] - $now);

        cache()->put($cacheKey, $data, $ttlSeconds);

        if ($data['count'] > $maxAttempts) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak request. Coba lagi sebentar.',
            ], 429)->withHeaders($this->headers($maxAttempts, 0, (int) $data['reset']));
        }

        $response = $next($request);

        foreach ($this->headers($maxAttempts, $remaining, (int) $data['reset']) as $name => $value) {
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
