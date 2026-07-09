<?php

use App\Models\AttendanceRecord;
use App\Models\Membership;
use App\Models\MembershipPackage;
use App\Models\PaymentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function adminToken(): string
{
    $admin = User::factory()->admin()->create([
        'password' => 'password123',
    ]);

    $response = test()->postJson('/v1/auth/login', [
        'identifier' => $admin->email,
        'password' => 'password123',
    ]);

    return $response->json('data.access_token');
}

it('exports finance report as pdf with payment data', function () {
    $token = adminToken();
    $member = User::factory()->create(['role' => 'member']);
    $package = MembershipPackage::factory()->create();
    $membership = Membership::factory()->create([
        'user_id' => $member->id,
        'package_id' => $package->id,
    ]);

    PaymentRecord::query()->create([
        'user_id' => $member->id,
        'membership_id' => $membership->id,
        'amount' => 250000,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'reference_number' => 'PAY-001',
        'status' => 'completed',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/admin/reports/export?report_type=finance&format=pdf');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $downloadUrl = $response->json('data.download_url');
    expect($downloadUrl)->toContain('.pdf');

    $relativePath = str_replace('/storage/', '', parse_url($downloadUrl, PHP_URL_PATH));
    Storage::disk('public')->assertExists($relativePath);

    $contents = Storage::disk('public')->get($relativePath);
    expect($contents)->not->toBeEmpty();
    expect(strlen($contents))->toBeGreaterThan(1000);
});

it('exports finance report as excel with payment data', function () {
    $token = adminToken();
    $member = User::factory()->create(['role' => 'member', 'name' => 'Budi Santoso']);
    $package = MembershipPackage::factory()->create(['name' => 'Paket Bulanan']);
    $membership = Membership::factory()->create([
        'user_id' => $member->id,
        'package_id' => $package->id,
    ]);

    PaymentRecord::query()->create([
        'user_id' => $member->id,
        'membership_id' => $membership->id,
        'amount' => 150000,
        'payment_method' => 'qris',
        'payment_date' => now()->toDateString(),
        'reference_number' => 'PAY-002',
        'status' => 'completed',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/admin/reports/export?report_type=finance&format=excel');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $downloadUrl = $response->json('data.download_url');
    expect($downloadUrl)->toContain('.xlsx');

    $relativePath = str_replace('/storage/', '', parse_url($downloadUrl, PHP_URL_PATH));
    Storage::disk('public')->assertExists($relativePath);
    expect(Storage::disk('public')->size($relativePath))->toBeGreaterThan(100);
});

it('exports attendance report as pdf with attendance data', function () {
    $token = adminToken();
    $member = User::factory()->create(['role' => 'member', 'name' => 'Andi Wijaya']);

    AttendanceRecord::query()->create([
        'user_id' => $member->id,
        'check_in_time' => now(),
        'check_out_time' => now()->addHour(),
        'location' => 'Main Entrance',
        'verification_status' => 'verified',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/admin/reports/export?report_type=attendance&format=pdf');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $downloadUrl = $response->json('data.download_url');
    expect($downloadUrl)->toContain('.pdf');

    $relativePath = str_replace('/storage/', '', parse_url($downloadUrl, PHP_URL_PATH));
    Storage::disk('public')->assertExists($relativePath);
    expect(strlen(Storage::disk('public')->get($relativePath)))->toBeGreaterThan(1000);
});

it('exports large attendance pdf by truncating detail rows', function () {
    $token = adminToken();
    $member = User::factory()->create(['role' => 'member']);

    for ($i = 0; $i < 300; $i++) {
        AttendanceRecord::query()->create([
            'user_id' => $member->id,
            'check_in_time' => now()->subMinutes($i),
            'location' => 'Main Entrance',
            'verification_status' => 'verified',
        ]);
    }

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/admin/reports/export?report_type=attendance&format=pdf');

    $response->assertOk()
        ->assertJsonPath('success', true);
});

it('exports members and attendance reports', function () {
    $token = adminToken();
    $member = User::factory()->create(['role' => 'member']);

    AttendanceRecord::query()->create([
        'user_id' => $member->id,
        'check_in_time' => now(),
        'location' => 'Main Entrance',
        'verification_status' => 'verified',
    ]);

    $membersPdf = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/admin/reports/export?report_type=members&format=pdf');
    $membersPdf->assertOk();

    $attendanceExcel = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/admin/reports/export?report_type=attendance&format=excel');
    $attendanceExcel->assertOk()
        ->assertJsonPath('success', true);
});

it('rejects invalid report date range', function () {
    $token = adminToken();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/v1/admin/reports/export?report_type=finance&format=pdf&from=2026-07-10&to=2026-07-01');

    $response->assertStatus(400);
});
