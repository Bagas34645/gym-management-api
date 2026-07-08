<?php

namespace App\Services\Membership;

use App\Models\Membership;
use App\Models\MembershipRenewal;
use App\Models\PaymentRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipFulfillmentService
{
    public function fulfill(
        MembershipRenewal $renewal,
        ?string $verifiedBy = null,
        ?string $referenceNumber = null,
    ): Membership {
        return DB::transaction(function () use ($renewal, $verifiedBy, $referenceNumber) {
            $renewal = MembershipRenewal::query()
                ->with(['membership', 'package'])
                ->lockForUpdate()
                ->findOrFail($renewal->id);

            if ($renewal->status === 'approved') {
                return $renewal->membership;
            }

            $membership = $renewal->membership;

            Membership::query()
                ->where('user_id', $renewal->user_id)
                ->where('status', 'active')
                ->where('id', '!=', $membership->id)
                ->update(['status' => 'expired']);

            $membership->update([
                'status' => 'active',
                'package_id' => $renewal->package_id,
                'end_date' => $renewal->new_end_date->toDateString(),
                'payment_method' => $renewal->payment_method,
                'payment_status' => 'completed',
            ]);

            $renewal->update([
                'status' => 'approved',
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
            ]);

            PaymentRecord::query()->create([
                'user_id' => $renewal->user_id,
                'membership_id' => $membership->id,
                'renewal_id' => $renewal->id,
                'amount' => $renewal->amount_paid,
                'payment_method' => $renewal->payment_method,
                'payment_date' => now()->toDateString(),
                'reference_number' => $referenceNumber ?? 'PAY-'.strtoupper(Str::random(10)),
                'status' => 'completed',
            ]);

            return $membership->fresh('package');
        });
    }

    public function reject(MembershipRenewal $renewal, ?string $verifiedBy = null): void
    {
        DB::transaction(function () use ($renewal, $verifiedBy) {
            $renewal = MembershipRenewal::query()
                ->with('membership')
                ->lockForUpdate()
                ->findOrFail($renewal->id);

            if (in_array($renewal->status, ['approved', 'rejected'], true)) {
                return;
            }

            $renewal->update([
                'status' => 'rejected',
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
            ]);

            $membership = $renewal->membership;
            if ($membership && $membership->status === 'pending_verification') {
                $membership->update([
                    'status' => 'inactive',
                    'payment_status' => 'failed',
                ]);
            }
        });
    }
}
