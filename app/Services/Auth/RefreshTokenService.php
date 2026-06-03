<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;

class RefreshTokenService
{
    public function issue(User $user): string
    {
        $plain = Str::random(64);

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plain),
            'expires_at' => now()->addSeconds(config('jwt.refresh_ttl')),
        ]);

        return $plain;
    }

    public function rotate(string $plainToken): array
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

        $jwt = app(JwtService::class);
        $newRefresh = $this->issue($user);

        return [
            'access_token' => $jwt->createAccessToken($user),
            'refresh_token' => $newRefresh,
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
}
