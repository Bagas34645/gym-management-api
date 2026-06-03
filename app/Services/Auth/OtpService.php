<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function send(string $identifier, string $method): int
    {
        $code = (string) random_int(100000, 999999);
        $ttl = config('gym.otp_ttl');

        Cache::put($this->cacheKey($identifier), [
            'code' => $code,
            'method' => $method,
        ], $ttl);

        if ($method === 'email' && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::raw("Kode OTP Anda: {$code}. Berlaku {$ttl} detik.", function ($message) use ($identifier) {
                    $message->to($identifier)->subject('Reset Password - Gym Management');
                });
            } catch (\Throwable $e) {
                Log::warning('OTP email failed', ['error' => $e->getMessage()]);
            }
        } else {
            Log::info('OTP SMS (dev)', ['identifier' => $identifier, 'code' => $code]);
        }

        return $ttl;
    }

    public function verify(string $identifier, string $code): bool
    {
        $stored = Cache::get($this->cacheKey($identifier));

        if (! $stored || ($stored['code'] ?? '') !== $code) {
            return false;
        }

        Cache::put($this->verifiedKey($identifier), true, config('gym.otp_ttl'));

        return true;
    }

    public function isVerified(string $identifier): bool
    {
        return (bool) Cache::get($this->verifiedKey($identifier), false);
    }

    public function clear(string $identifier): void
    {
        Cache::forget($this->cacheKey($identifier));
        Cache::forget($this->verifiedKey($identifier));
    }

    private function cacheKey(string $identifier): string
    {
        return 'otp:'.hash('sha256', strtolower(trim($identifier)));
    }

    private function verifiedKey(string $identifier): string
    {
        return 'otp_verified:'.hash('sha256', strtolower(trim($identifier)));
    }
}
