<?php

use App\Models\Membership;
use App\Models\MembershipPackage;
use App\Models\MembershipRenewal;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Services\Payment\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.midtrans.server_key' => 'test-server-key',
        'services.midtrans.client_key' => 'test-client-key',
        'services.midtrans.is_production' => false,
    ]);
});

function loginAsMember(): array
{
    $user = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password123',
    ]);

    $login = test()->postJson('/v1/auth/login', [
        'identifier' => $user->email,
        'password' => 'password123',
    ]);

    return [
        'user' => $user,
        'token' => $login->json('data.access_token'),
    ];
}

function midtransSignature(string $orderId, string $statusCode, string $grossAmount): string
{
    return hash('sha512', $orderId.$statusCode.$grossAmount.config('services.midtrans.server_key'));
}

it('creates snap payment and returns token', function () {
    $auth = loginAsMember();
    $package = MembershipPackage::factory()->create(['status' => 'active', 'price' => 150000]);

    $this->mock(MidtransService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createSnapTransaction')
            ->once()
            ->andReturn([
                'snap_token' => 'snap-token-123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-123',
            ]);
    });

    $response = $this->withHeader('Authorization', 'Bearer '.$auth['token'])
        ->postJson('/v1/memberships/payments/snap', [
            'package_id' => $package->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.snap_token', 'snap-token-123')
        ->assertJsonPath('data.client_key', 'test-client-key');

    $this->assertDatabaseHas('membership_renewals', [
        'user_id' => $auth['user']->id,
        'package_id' => $package->id,
        'payment_method' => 'midtrans',
        'status' => 'pending_payment',
    ]);
});

it('creates cash renewal with pending verification', function () {
    $auth = loginAsMember();
    $package = MembershipPackage::factory()->create(['status' => 'active']);

    $response = $this->withHeader('Authorization', 'Bearer '.$auth['token'])
        ->postJson('/v1/memberships/renew', [
            'package_id' => $package->id,
            'payment_method' => 'cash',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_verification');

    $this->assertDatabaseHas('membership_renewals', [
        'user_id' => $auth['user']->id,
        'payment_method' => 'cash',
        'status' => 'pending_verification',
    ]);
});

it('rejects invalid midtrans webhook signature', function () {
    $response = $this->postJson('/v1/payments/midtrans/notification', [
        'order_id' => 'GYM-test',
        'status_code' => '200',
        'gross_amount' => '100000.00',
        'signature_key' => 'invalid',
        'transaction_status' => 'settlement',
    ]);

    $response->assertForbidden();
});

it('fulfills membership on successful midtrans webhook', function () {
    $user = User::factory()->create(['role' => 'member', 'status' => 'active']);
    $package = MembershipPackage::factory()->create(['status' => 'active', 'price' => 200000]);
    $membership = Membership::factory()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'status' => 'active',
        'start_date' => now()->subDays(10),
        'end_date' => now()->addDays(20),
    ]);

    $renewal = MembershipRenewal::factory()->create([
        'membership_id' => $membership->id,
        'user_id' => $user->id,
        'package_id' => $package->id,
        'status' => 'pending_payment',
        'payment_method' => 'midtrans',
        'amount_paid' => 200000,
        'previous_end_date' => $membership->end_date,
        'new_end_date' => $membership->end_date->copy()->addDays($package->duration_days),
        'midtrans_order_id' => 'GYM-'.$membership->id,
    ]);

    $orderId = $renewal->midtrans_order_id;
    $grossAmount = '200000.00';

    $payload = [
        'order_id' => $orderId,
        'status_code' => '200',
        'gross_amount' => $grossAmount,
        'signature_key' => midtransSignature($orderId, '200', $grossAmount),
        'transaction_status' => 'settlement',
        'transaction_id' => 'txn-123',
    ];

    $response = $this->postJson('/v1/payments/midtrans/notification', $payload);

    $response->assertOk();

    $renewal->refresh();
    $membership->refresh();

    expect($renewal->status)->toBe('approved')
        ->and($renewal->midtrans_transaction_status)->toBe('settlement')
        ->and($membership->status)->toBe('active');

    $this->assertDatabaseHas('payment_records', [
        'renewal_id' => $renewal->id,
        'payment_method' => 'midtrans',
        'reference_number' => $orderId,
        'status' => 'completed',
    ]);
});

it('is idempotent when midtrans webhook is sent twice', function () {
    $user = User::factory()->create(['role' => 'member', 'status' => 'active']);
    $package = MembershipPackage::factory()->create(['status' => 'active', 'price' => 100000]);
    $membership = Membership::factory()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'status' => 'active',
    ]);

    $renewal = MembershipRenewal::factory()->create([
        'membership_id' => $membership->id,
        'user_id' => $user->id,
        'package_id' => $package->id,
        'status' => 'pending_payment',
        'payment_method' => 'midtrans',
        'amount_paid' => 100000,
        'previous_end_date' => $membership->end_date,
        'new_end_date' => $membership->end_date->copy()->addDays(30),
        'midtrans_order_id' => 'GYM-idempotent-test',
    ]);

    $payload = [
        'order_id' => $renewal->midtrans_order_id,
        'status_code' => '200',
        'gross_amount' => '100000.00',
        'signature_key' => midtransSignature($renewal->midtrans_order_id, '200', '100000.00'),
        'transaction_status' => 'settlement',
        'transaction_id' => 'txn-456',
    ];

    $this->postJson('/v1/payments/midtrans/notification', $payload)->assertOk();
    $this->postJson('/v1/payments/midtrans/notification', $payload)->assertOk();

    expect(PaymentRecord::query()->where('renewal_id', $renewal->id)->count())->toBe(1);
});

it('returns payment status for member', function () {
    $auth = loginAsMember();
    $package = MembershipPackage::factory()->create();
    $membership = Membership::factory()->create([
        'user_id' => $auth['user']->id,
        'package_id' => $package->id,
        'status' => 'active',
    ]);

    $renewal = MembershipRenewal::factory()->create([
        'membership_id' => $membership->id,
        'user_id' => $auth['user']->id,
        'package_id' => $package->id,
        'status' => 'pending_payment',
        'payment_method' => 'midtrans',
        'midtrans_order_id' => 'GYM-status-test',
        'previous_end_date' => $membership->end_date,
        'new_end_date' => $membership->end_date->copy()->addDays(30),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$auth['token'])
        ->getJson('/v1/memberships/payments/GYM-status-test/status');

    $response->assertOk()
        ->assertJsonPath('data.order_id', 'GYM-status-test')
        ->assertJsonPath('data.is_paid', false);
});
