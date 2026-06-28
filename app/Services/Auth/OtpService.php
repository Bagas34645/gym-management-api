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

    public function send(string $identifier, string $method): int
    {
        $code = (string) random_int(100000, 999999);
        $ttl = config('gym.otp_ttl');

        EmailOtp::updateOrCreate(
            [
                'identifier' => strtolower(trim($identifier)),
                'method'     => $method,
            ],
            [
                'code_hash'  => Hash::make($code),
                'expires_at' => now()->addSeconds($ttl),
            ]
        );

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
            Log::info('OTP SMS (dev)', ['identifier' => $identifier, 'code' => $code]);
        }

        // ✅ Atomic: set key hanya jika belum ada, lalu increment
        $resendKey = $this->resendKey($identifier);
        Cache::add($resendKey, 0, self::RESEND_WINDOW);
        Cache::increment($resendKey);

        return $ttl;
    }

    // ✅ Pengecekan limit dipindah ke sini agar tidak tersebar
    public function checkResendLimit(string $identifier): bool
    {
        $count = Cache::get($this->resendKey($identifier), 0);
        return $count < self::RESEND_LIMIT;
    }

    public function verify(string $identifier, string $code): bool
    {
        $identifierNormalized = strtolower(trim($identifier));

        $otp = EmailOtp::query()
            ->where('identifier', $identifierNormalized)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
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
        $identifierNormalized = strtolower(trim($identifier));
        EmailOtp::query()->where('identifier', $identifierNormalized)->delete();
        Cache::forget($this->verifiedKey($identifier));
        Cache::forget($this->resendKey($identifier));
    }

    private function resendKey(string $identifier): string
    {
        return 'otp_resend:' . hash('sha256', strtolower(trim($identifier)));
    }

    private function verifiedKey(string $identifier): string
    {
        return 'otp_verified:' . hash('sha256', strtolower(trim($identifier)));
    }

    // cacheKey tidak dipakai, dihapus agar tidak membingungkan
}