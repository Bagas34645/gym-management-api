<?php

namespace App\Services\Auth;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    private const RESEND_LIMIT = 3;

    private const RESEND_WINDOW = 3600; // 1 jam

    private const MAX_VERIFY_ATTEMPTS = 5;

    public function send(string $identifier, string $method): int
    {
        $code = (string) random_int(100000, 999999);
        $ttl = config('gym.otp_ttl');

        EmailOtp::updateOrCreate(
            [
                'identifier' => strtolower(trim($identifier)),
                'method' => $method,
            ],
            [
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addSeconds($ttl),
            ]
        );

        // OTP baru dikirim, reset counter percobaan verifikasi yang gagal.
        Cache::forget($this->attemptsKey($identifier));

        if ($method === 'email' && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($identifier)->queue(new OtpMail($code, $ttl));
            } catch (\Throwable $e) {
                Log::warning('OTP email failed', ['error' => $e->getMessage()]);
                try {
                    Mail::raw("Kode OTP Anda: {$code}. Berlaku {$ttl} detik.", function ($message) use ($identifier) {
                        $message->to($identifier)->subject('Kode OTP Verifikasi');
                    });
                } catch (\Throwable $e) {
                    Log::warning('OTP fallback send failed', ['error' => $e->getMessage()]);
                }
            }
        } else {
            Log::info('OTP SMS requested (dev only — configure SMS provider for production)', [
                'identifier' => $identifier,
            ]);
        }

        return $ttl;
    }

    /**
     * Catat percobaan resend secara atomik. Mengembalikan false bila limit terlampaui.
     */
    public function registerResendAttempt(string $identifier): bool
    {
        $key = $this->resendKey($identifier);
        Cache::add($key, 0, self::RESEND_WINDOW);
        $count = Cache::increment($key);

        return $count <= self::RESEND_LIMIT;
    }

    public function verify(string $identifier, string $code): bool
    {
        $identifierNormalized = strtolower(trim($identifier));
        $attemptsKey = $this->attemptsKey($identifier);

        // Lindungi dari brute force: tolak setelah terlalu banyak percobaan gagal.
        if ((int) Cache::get($attemptsKey, 0) >= self::MAX_VERIFY_ATTEMPTS) {
            return false;
        }

        $otp = EmailOtp::query()
            ->where('identifier', $identifierNormalized)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp || ! Hash::check($code, $otp->code_hash)) {
            Cache::add($attemptsKey, 0, config('gym.otp_ttl'));
            Cache::increment($attemptsKey);

            return false;
        }

        Cache::forget($attemptsKey);
        Cache::put($this->verifiedKey($identifier), true, config('gym.otp_ttl'));

        return true;
    }

    public function isVerified(string $identifier): bool
    {
        return (bool) Cache::get($this->verifiedKey($identifier), false);
    }

    public function clear(string $identifier): void
    {
        $identifierNormalized = strtolower(trim($identifier));
        EmailOtp::query()->where('identifier', $identifierNormalized)->delete();
        Cache::forget($this->verifiedKey($identifier));
        Cache::forget($this->resendKey($identifier));
        Cache::forget($this->attemptsKey($identifier));
    }

    private function resendKey(string $identifier): string
    {
        return 'otp_resend:'.hash('sha256', strtolower(trim($identifier)));
    }

    private function verifiedKey(string $identifier): string
    {
        return 'otp_verified:'.hash('sha256', strtolower(trim($identifier)));
    }

    private function attemptsKey(string $identifier): string
    {
        return 'otp_attempts:'.hash('sha256', strtolower(trim($identifier)));
    }
}
