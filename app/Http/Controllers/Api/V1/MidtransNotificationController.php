<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Controller as ApiController;
use App\Models\MembershipRenewal;
use App\Services\Membership\MembershipFulfillmentService;
use App\Services\Payment\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransNotificationController extends ApiController
{
    public function __construct(
        private readonly MidtransService $midtransService,
        private readonly MembershipFulfillmentService $fulfillmentService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->midtransService->verifyNotificationSignature($payload)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $renewal = MembershipRenewal::query()
            ->where('midtrans_order_id', $payload['order_id'] ?? null)
            ->first();

        if (! $renewal) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $transactionStatus = $payload['transaction_status'] ?? '';

        $renewal->update([
            'midtrans_transaction_id' => $payload['transaction_id'] ?? $renewal->midtrans_transaction_id,
            'midtrans_transaction_status' => $transactionStatus,
            'midtrans_raw_response' => $payload,
        ]);

        if ($this->midtransService->isSuccessfulStatus($transactionStatus)) {
            $this->fulfillmentService->fulfill(
                $renewal,
                referenceNumber: $renewal->midtrans_order_id,
            );
        } elseif ($this->midtransService->isFailedStatus($transactionStatus)) {
            $this->fulfillmentService->reject($renewal);
        }

        return response()->json(['message' => 'OK']);
    }
}
