<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RefreshTokenService
{
    /**
     * @param  array{ip_address?: ?string, user_agent?: ?string, platform?: ?string, browser?: ?string}|null  $meta
     * @return array{token: string, id: int}
     */
    public function issue(User $user, ?array $meta = null): array
    {
        $plain = Str::random(64);
        $parsed = self::parseUserAgent($meta['user_agent'] ?? null);

        $record = RefreshToken::query()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plain),
            'expires_at' => now()->addSeconds(config('jwt.refresh_ttl')),
            'ip_address' => $meta['ip_address'] ?? null,
            'user_agent' => $meta['user_agent'] ?? null,
            'platform' => $meta['platform'] ?? $parsed['platform'],
            'browser' => $meta['browser'] ?? $parsed['browser'],
            'last_used_at' => now(),
        ]);

        return [
            'token' => $plain,
            'id' => (int) $record->id,
        ];
    }

    /**
     * @param  array{ip_address?: ?string, user_agent?: ?string}|null  $meta
     */
    public function rotate(string $plainToken, ?array $meta = null): array
    {
        $hashed = hash('sha256', $plainToken);

        $record = RefreshToken::query()
            ->where('token', $hashed)
            ->valid()
            ->first();

        if (! $record) {
            throw new ApiException('Refresh token tidak valid', ErrorCode::AuthInvalidRefresh, 401);
        }

        $record->update(['revoked_at' => now()]);
        $user = $record->user;

        $copiedMeta = [
            'ip_address' => $meta['ip_address'] ?? $record->ip_address,
            // Keep original device fingerprint; refresh is often from a BFF/server UA.
            'user_agent' => $record->user_agent,
            'platform' => $record->platform,
            'browser' => $record->browser,
        ];

        $jwt = app(JwtService::class);
        $issued = $this->issue($user, $copiedMeta);

        return [
            'access_token' => $jwt->createAccessToken($user, $issued['id']),
            'refresh_token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.access_ttl'),
            'user' => $user,
        ];
    }

    public function revoke(string $plainToken): void
    {
        $hashed = hash('sha256', $plainToken);

        RefreshToken::query()
            ->where('token', $hashed)
            ->valid()
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(User $user): void
    {
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function listForUser(User $user, ?string $currentPlain = null): Collection
    {
        $currentHash = $currentPlain ? hash('sha256', $currentPlain) : null;

        return RefreshToken::query()
            ->where('user_id', $user->id)
            ->valid()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (RefreshToken $token) use ($currentHash) {
                $isCurrent = $currentHash !== null && hash_equals($token->token, $currentHash);

                return [
                    'id' => $token->id,
                    'platform' => $token->platform,
                    'browser' => $token->browser,
                    'ip_address' => $token->ip_address,
                    'logged_in_at' => $token->created_at?->toIso8601String(),
                    'last_active_at' => ($token->last_used_at ?? $token->created_at)?->toIso8601String(),
                    'logged_out_at' => null,
                    'is_current' => $isCurrent,
                    'status' => $isCurrent ? 'current' : 'other',
                ];
            });
    }

    /**
     * @return array{was_current: bool}
     */
    public function revokeById(User $user, int $id, ?string $currentPlain = null): array
    {
        $record = RefreshToken::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->valid()
            ->first();

        if (! $record) {
            throw new ApiException('Sesi tidak ditemukan', ErrorCode::AuthInvalidRefresh, 404);
        }

        $currentHash = $currentPlain ? hash('sha256', $currentPlain) : null;
        $wasCurrent = $currentHash !== null && hash_equals($record->token, $currentHash);

        $record->update(['revoked_at' => now()]);

        return ['was_current' => $wasCurrent];
    }

    /**
     * @return array{platform: ?string, browser: ?string}
     */
    public static function parseUserAgent(?string $ua): array
    {
        if (! $ua) {
            return ['platform' => null, 'browser' => null];
        }

        $platform = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS'), str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Unknown',
        };

        return ['platform' => $platform, 'browser' => $browser];
    }
}
