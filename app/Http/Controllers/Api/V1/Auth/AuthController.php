<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\User;
use App\Services\Auth\JwtService;
use App\Services\Auth\LoginAttemptService;
use App\Services\Auth\OtpService;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        private JwtService $jwt,
        private RefreshTokenService $refreshTokens,
        private OtpService $otp,
        private LoginAttemptService $loginAttempts,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'regex:/^08\d{8,11}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => 'member',
            'status' => 'active',
        ]);

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ], 'Registrasi berhasil', null, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_token' => ['nullable', 'string', 'max:500'],
        ]);

        $this->loginAttempts->ensureNotLocked($data['identifier']);

        $user = User::query()
            ->where(fn ($q) => $q->where('email', $data['identifier'])->orWhere('phone', $data['identifier']))
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->loginAttempts->recordFailure($data['identifier']);
            throw new ApiException('Email atau password salah', ErrorCode::AuthInvalidToken, 401);
        }

        if ($user->status !== 'active') {
            throw new ApiException('Akun tidak aktif', ErrorCode::AuthInvalidToken, 403);
        }

        $this->loginAttempts->clear($data['identifier']);

        if (! empty($data['device_token'])) {
            $user->update(['device_token' => $data['device_token']]);
        }

        $user->update(['last_login_at' => now()]);

        $membershipStatus = $user->activeMembership ? 'active' : 'inactive';

        return $this->success([
            'access_token' => $this->jwt->createAccessToken($user),
            'refresh_token' => $this->refreshTokens->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.access_ttl'),
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'membership_status' => $membershipStatus,
            ],
        ]);
    }

    public function googleLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'device_token' => ['nullable', 'string', 'max:500'],
        ]);

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $data['id_token'],
        ]);

        if (! $response->successful()) {
            throw new ApiException('Google token tidak valid', ErrorCode::AuthInvalidToken, 401);
        }

        $payload = $response->json();
        $email = $payload['email'] ?? null;

        if (! $email) {
            throw new ApiException('Google token tidak valid', ErrorCode::AuthInvalidToken, 401);
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $payload['name'] ?? 'Google User',
                'phone' => '08'.substr(md5($email), 0, 10),
                'password' => bcrypt(str()->random(32)),
                'role' => 'member',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        if (! empty($data['device_token'])) {
            $user->update(['device_token' => $data['device_token']]);
        }

        $user->update(['last_login_at' => now()]);

        return $this->success([
            'access_token' => $this->jwt->createAccessToken($user),
            'refresh_token' => $this->refreshTokens->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.access_ttl'),
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'membership_status' => $user->activeMembership ? 'active' : 'inactive',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $refresh = $request->input('refresh_token');
        if ($refresh) {
            $this->refreshTokens->revoke($refresh);
        }

        return $this->success(null, 'Logout berhasil');
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);
        $tokens = $this->refreshTokens->rotate($data['refresh_token']);

        return $this->success([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'method' => ['required', 'in:email,sms'],
        ]);

        $user = User::query()
            ->where(fn ($q) => $q->where('email', $data['identifier'])->orWhere('phone', $data['identifier']))
            ->first();

        if (! $user) {
            return $this->success(['expires_in' => config('gym.otp_ttl')], 'Kode OTP dikirim ke email/HP');
        }

        $target = $data['method'] === 'email' ? $user->email : $user->phone;
        $expires = $this->otp->send($target, $data['method']);

        return $this->success(['expires_in' => $expires], 'Kode OTP dikirim ke email/HP');
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $this->otp->verify($data['identifier'], $data['code'])) {
            throw new ApiException('Kode OTP tidak valid', null, 422);
        }

        return $this->success(null, 'OTP terverifikasi');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! $this->otp->verify($data['identifier'], $data['code'])) {
            throw new ApiException('Kode OTP tidak valid', null, 422);
        }

        $user = User::query()
            ->where(fn ($q) => $q->where('email', $data['identifier'])->orWhere('phone', $data['identifier']))
            ->first();

        if (! $user) {
            throw new ApiException('Member tidak ditemukan', ErrorCode::MemberNotFound, 404);
        }

        $user->update(['password' => $data['password']]);
        $this->otp->clear($data['identifier']);
        $this->refreshTokens->revokeAllForUser($user);

        return $this->success(null, 'Password berhasil direset');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success($this->profilePayload($user));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'age' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'height_cm' => ['sometimes', 'integer', 'min:50', 'max:300'],
            'weight_kg' => ['sometimes', 'numeric', 'min:20', 'max:500'],
            'fitness_goal' => ['sometimes', 'string', 'max:100'],
            'profile_photo' => ['sometimes', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profiles', 'public');
            $data['profile_photo_url'] = asset('storage/'.$path);
            unset($data['profile_photo']);
        }

        $user->update(collect($data)->except('profile_photo')->toArray());

        return $this->success($this->profilePayload($user->fresh()), 'Profil berhasil diperbarui');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw new ApiException('Password saat ini salah', httpStatus: 422, errors: ['current_password' => ['Password saat ini salah']]);
        }

        $user->update(['password' => $data['password']]);

        return $this->success(null, 'Password berhasil diubah');
    }

    private function profilePayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'profile_photo_url' => $user->profile_photo_url,
            'age' => $user->age,
            'height_cm' => $user->height_cm,
            'weight_kg' => $user->weight_kg,
            'fitness_goal' => $user->fitness_goal,
            'membership_status' => $user->activeMembership ? 'active' : 'inactive',
        ];
    }
}
