<?php

namespace App\Services\Payment;

use App\Models\MembershipRenewal;
use App\Models\User;
use Midtrans\Snap;

class MidtransService
{
    public function createSnapTransaction(MembershipRenewal $renewal, User $user): array
    {
        $renewal->loadMissing('package');

        $amount = (int) round((float) $renewal->amount_paid);

        $params = [
            'transaction_details' => [
                'order_id' => $renewal->midtrans_order_id,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'item_details' => [
                [
                    'id' => (string) $renewal->package_id,
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => $renewal->package->name,
                ],
            ],
        ];

        $response = Snap::createTransaction($params);

        return [
            'snap_token' => $response->token,
            'redirect_url' => $response->redirect_url,
        ];
    }

    public function verifyNotificationSignature(array $payload): bool
    {
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signatureKey = $payload['signature_key'] ?? '';

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
            return false;
        }

        $serverKey = config('services.midtrans.server_key');
        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, $signatureKey);
    }

    public function isSuccessfulStatus(string $status): bool
    {
        return in_array($status, ['settlement', 'capture'], true);
    }

    public function isFailedStatus(string $status): bool
    {
        return in_array($status, ['deny', 'cancel', 'expire', 'failure'], true);
    }

    public function snapRedirectBaseUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v4/redirection/'
            : 'https://app.sandbox.midtrans.com/snap/v4/redirection/';
    }
}
