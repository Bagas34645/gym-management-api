<?php

use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
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
    $refresh = app(RefreshTokenService::class)->issue($user);

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
