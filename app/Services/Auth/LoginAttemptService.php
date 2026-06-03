<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Cache;

class LoginAttemptService
{
    public function ensureNotLocked(string $identifier): void
    {
        if (Cache::has($this->lockKey($identifier))) {
            throw new ApiException(
                'Terlalu banyak percobaan login, akun dikunci sementara',
                ErrorCode::AuthTooManyAttempts,
                429,
            );
        }
    }

    public function recordFailure(string $identifier): void
    {
        $key = $this->attemptKey($identifier);
        $attempts = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addMinutes(config('gym.login_lockout_minutes')));

        if ($attempts >= config('gym.login_max_attempts')) {
            Cache::put(
                $this->lockKey($identifier),
                true,
                now()->addMinutes(config('gym.login_lockout_minutes')),
            );
            Cache::forget($key);
        }
    }

    public function clear(string $identifier): void
    {
        Cache::forget($this->attemptKey($identifier));
        Cache::forget($this->lockKey($identifier));
    }

    private function attemptKey(string $identifier): string
    {
        return 'login_attempts:'.hash('sha256', strtolower(trim($identifier)));
    }

    private function lockKey(string $identifier): string
    {
        return 'login_locked:'.hash('sha256', strtolower(trim($identifier)));
    }
}
