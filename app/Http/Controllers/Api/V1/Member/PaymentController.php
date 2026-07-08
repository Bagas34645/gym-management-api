<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\MembershipPackage;
use App\Models\MembershipRenewal;
use App\Services\Membership\MembershipFulfillmentService;
use App\Services\Membership\MembershipRenewalService;
use App\Services\Payment\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly MembershipRenewalService $renewalService,
        private readonly MidtransService $midtransService,
        private readonly MembershipFulfillmentService $fulfillmentService,
    ) {}

    public function createSnap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'package_id' => ['required', 'uuid', 'exists:membership_packages,id'],
        ]);

        $package = MembershipPackage::query()
            ->where('status', 'active')
            ->find($data['package_id']);

        if (! $package) {
            throw new ApiException('Paket membership tidak ditemukan atau tidak aktif', ErrorCode::MembershipPackageInvalid, 400);
        }

        $user = $request->user();

        if ($this->renewalService->hasPendingMidtransRenewal($user, $package->id)) {
            throw new ApiException('Anda masih memiliki pembayaran Midtrans yang belum selesai', null, 409);
        }

        $renewal = $this->renewalService->createRenewal(
            $user,
            $package,
            'midtrans',
            'pending_payment',
        );

        try {
            $snap = $this->midtransService->createSnapTransaction($renewal, $user);
        } catch (\Throwable $e) {
            $this->fulfillmentService->reject($renewal);
            throw new ApiException('Gagal membuat transaksi Midtrans. Silakan coba lagi.', null, 502);
        }

        return $this->success([
            'renewal_id' => $renewal->id,
            'order_id' => $renewal->midtrans_order_id,
            'snap_token' => $snap['snap_token'],
            'redirect_url' => $snap['redirect_url'],
            'client_key' => config('services.midtrans.client_key'),
        ], 'Transaksi Midtrans berhasil dibuat', null, 201);
    }

    public function status(Request $request, string $orderId): JsonResponse
    {
        $renewal = MembershipRenewal::query()
            ->where('user_id', $request->user()->id)
            ->where('midtrans_order_id', $orderId)
            ->with('package')
            ->firstOrFail();

        return $this->success([
            'order_id' => $renewal->midtrans_order_id,
            'renewal_id' => $renewal->id,
            'renewal_status' => $renewal->status,
            'transaction_status' => $renewal->midtrans_transaction_status,
            'package_name' => $renewal->package->name,
            'new_end_date' => $renewal->new_end_date->format('Y-m-d'),
            'is_paid' => $renewal->status === 'approved',
        ]);
    }
}
