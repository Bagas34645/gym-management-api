<?php

use App\Models\Membership;
use App\Models\MembershipPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists public membership packages', function () {
    MembershipPackage::factory()->create(['name' => 'Bulanan', 'status' => 'active']);

    $response = $this->getJson('/v1/memberships/packages');

    $response->assertOk()->assertJsonPath('success', true);
});

it('returns active membership for member', function () {
    $user = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password123',
    ]);
    $package = MembershipPackage::factory()->create();
    Membership::factory()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'status' => 'active',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(25),
    ]);

    $login = $this->postJson('/v1/auth/login', [
        'identifier' => $user->email,
        'password' => 'password123',
    ]);

    $token = $login->json('data.access_token');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/memberships/active');

    $response->assertOk()->assertJsonPath('data.package_name', $package->name);
});
