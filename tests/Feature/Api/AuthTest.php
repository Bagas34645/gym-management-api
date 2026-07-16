<?php

use App\Models\EmailOtp;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function authTokenFor(User $user, string $password = 'password123'): string
{
    $response = test()->postJson('/v1/auth/login', [
        'identifier' => $user->email,
        'password' => $password,
    ]);

    return $response->json('data.access_token');
}

it('registers a new member', function () {
    $response = $this->postJson('/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '081234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'john@example.com');

    $this->assertDatabaseHas('users', ['email' => 'john@example.com', 'is_verified' => false]);
    $this->assertDatabaseHas('email_otps', ['identifier' => 'john@example.com']);
});

it('blocks login for unverified accounts', function () {
    User::factory()->unverified()->create([
        'email' => 'unverified@test.com',
        'phone' => '081222333444',
        'password' => 'password123',
        'role' => 'member',
        'status' => 'active',
    ]);

    $response = $this->postJson('/v1/auth/login', [
        'identifier' => 'unverified@test.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(403)->assertJsonPath('success', false);
});

it('verifies otp during registration and then allows login', function () {
    $this->postJson('/v1/auth/register', [
        'name' => 'Otp User',
        'email' => 'otp@example.com',
        'phone' => '081333444555',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(201);

    EmailOtp::query()
        ->where('identifier', 'otp@example.com')
        ->update([
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

    $this->postJson('/v1/auth/verify-otp', [
        'email' => 'otp@example.com',
        'code' => '123456',
    ])->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('users', ['email' => 'otp@example.com', 'is_verified' => true]);

    $this->postJson('/v1/auth/login', [
        'identifier' => 'otp@example.com',
        'password' => 'password123',
    ])->assertOk()->assertJsonStructure(['data' => ['access_token']]);
});

it('rejects invalid otp code', function () {
    $this->postJson('/v1/auth/register', [
        'name' => 'Bad Otp',
        'email' => 'badotp@example.com',
        'phone' => '081444555666',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(201);

    $this->postJson('/v1/auth/verify-otp', [
        'email' => 'badotp@example.com',
        'code' => '000000',
    ])->assertStatus(422)->assertJsonPath('success', false);
});

it('logs in with valid credentials', function () {
    User::factory()->create([
        'email' => 'member@test.com',
        'phone' => '081111111111',
        'password' => 'password123',
        'role' => 'member',
        'status' => 'active',
    ]);

    $response = $this->postJson('/v1/auth/login', [
        'identifier' => 'member@test.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'member']]);
});

it('returns profile for authenticated user', function () {
    $user = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password123',
    ]);

    $token = authTokenFor($user);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/auth/me');

    $response->assertOk()->assertJsonPath('data.id', $user->id);
});

it('refreshes access token', function () {
    $user = User::factory()->create(['role' => 'member', 'status' => 'active']);
    $refresh = app(RefreshTokenService::class)->issue($user)['token'];

    $response = $this->postJson('/v1/auth/refresh', ['refresh_token' => $refresh]);

    $response->assertOk()->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);
});

it('denies admin routes for members', function () {
    $user = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password123',
    ]);

    $token = authTokenFor($user);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/admin/dashboard/summary');

    $response->assertStatus(403);
});

it('lists login sessions and marks the current one', function () {
    $user = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password123',
    ]);

    $login = $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    ])->postJson('/v1/auth/login', [
        'identifier' => $user->email,
        'password' => 'password123',
    ]);

    $login->assertOk();
    $access = $login->json('data.access_token');
    $refresh = $login->json('data.refresh_token');

    app(RefreshTokenService::class)->issue($user, [
        'ip_address' => '10.0.0.2',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/119.0.0.0',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$access)
        ->getJson('/v1/auth/sessions?current_refresh_token='.urlencode($refresh));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data');

    $sessions = collect($response->json('data'));
    expect($sessions->where('is_current', true))->toHaveCount(1);
    expect($sessions->firstWhere('is_current', true)['status'])->toBe('current');
    expect($sessions->firstWhere('is_current', true)['platform'])->toBe('Linux');
    expect($sessions->firstWhere('is_current', true)['browser'])->toBe('Chrome');
});

it('revokes another login session by id', function () {
    $user = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password123',
    ]);

    $login = $this->postJson('/v1/auth/login', [
        'identifier' => $user->email,
        'password' => 'password123',
    ])->assertOk();

    $access = $login->json('data.access_token');
    $refresh = $login->json('data.refresh_token');

    $otherPlain = app(RefreshTokenService::class)->issue($user, [
        'ip_address' => '10.0.0.99',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/119.0.0.0',
    ])['token'];

    $otherId = \App\Models\RefreshToken::query()
        ->where('token', hash('sha256', $otherPlain))
        ->value('id');

    $this->withHeader('Authorization', 'Bearer '.$access)
        ->deleteJson('/v1/auth/sessions/'.$otherId, [
            'current_refresh_token' => $refresh,
        ])
        ->assertOk()
        ->assertJsonPath('data.was_current', false);

    $this->assertDatabaseHas('refresh_tokens', [
        'id' => $otherId,
    ]);

    expect(\App\Models\RefreshToken::query()->find($otherId)->revoked_at)->not->toBeNull();
});

it('rejects revoking a session owned by another user', function () {
    $user = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password123',
    ]);
    $other = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password123',
    ]);

    $login = $this->postJson('/v1/auth/login', [
        'identifier' => $user->email,
        'password' => 'password123',
    ])->assertOk();

    $otherPlain = app(RefreshTokenService::class)->issue($other)['token'];
    $otherId = \App\Models\RefreshToken::query()
        ->where('token', hash('sha256', $otherPlain))
        ->value('id');

    $this->withHeader('Authorization', 'Bearer '.$login->json('data.access_token'))
        ->deleteJson('/v1/auth/sessions/'.$otherId)
        ->assertStatus(404);
});

it('rejects access token after its session is revoked', function () {
    $user = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password123',
    ]);

    $login = $this->postJson('/v1/auth/login', [
        'identifier' => $user->email,
        'password' => 'password123',
    ])->assertOk();

    $access = $login->json('data.access_token');
    $refresh = $login->json('data.refresh_token');

    $sessionId = \App\Models\RefreshToken::query()
        ->where('token', hash('sha256', $refresh))
        ->value('id');

    $this->withHeader('Authorization', 'Bearer '.$access)
        ->getJson('/v1/auth/me')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$access)
        ->deleteJson('/v1/auth/sessions/'.$sessionId, [
            'current_refresh_token' => $refresh,
        ])
        ->assertOk()
        ->assertJsonPath('data.was_current', true);

    $this->withHeader('Authorization', 'Bearer '.$access)
        ->getJson('/v1/auth/me')
        ->assertStatus(401);
});
