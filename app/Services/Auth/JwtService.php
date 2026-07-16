<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    public function createAccessToken(User $user, int $sessionId): string
    {
        $now = time();
        $payload = [
            'iss' => config('jwt.issuer'),
            'iat' => $now,
            'exp' => $now + config('jwt.access_ttl'),
            'sub' => (string) $user->id,
            'role' => $user->role,
            'type' => 'access',
            'sid' => $sessionId,
        ];

        return JWT::encode($payload, $this->privateKey(), 'RS256');
    }

    public function parseAccessToken(string $token): User
    {
        try {
            $decoded = JWT::decode($token, new Key($this->publicKey(), 'RS256'));
        } catch (\Throwable) {
            throw new ApiException('Token tidak valid atau sudah kadaluarsa', ErrorCode::AuthInvalidToken, 401);
        }

        if (($decoded->type ?? null) !== 'access') {
            throw new ApiException('Token tidak valid atau sudah kadaluarsa', ErrorCode::AuthInvalidToken, 401);
        }

        $user = User::query()->find($decoded->sub);

        if (! $user || $user->status === 'suspended') {
            throw new ApiException('Token tidak valid atau sudah kadaluarsa', ErrorCode::AuthInvalidToken, 401);
        }

        $this->assertSessionActive((string) $user->id, $decoded->sid ?? null);

        return $user;
    }

    private function assertSessionActive(string $userId, mixed $sessionId): void
    {
        if ($sessionId === null || $sessionId === '') {
            throw new ApiException('Token tidak valid atau sudah kadaluarsa', ErrorCode::AuthInvalidToken, 401);
        }

        $active = RefreshToken::query()
            ->where('id', (int) $sessionId)
            ->where('user_id', $userId)
            ->valid()
            ->exists();

        if (! $active) {
            throw new ApiException('Token tidak valid atau sudah kadaluarsa', ErrorCode::AuthInvalidToken, 401);
        }
    }

    private function privateKey(): string
    {
        return file_get_contents($this->keyPath(config('jwt.private_key_path')));
    }

    private function publicKey(): string
    {
        return file_get_contents($this->keyPath(config('jwt.public_key_path')));
    }

    private function keyPath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }
}
