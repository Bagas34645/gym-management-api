<?php

namespace App\Services\Membership;

use App\Models\Membership;
use App\Models\MembershipPackage;
use App\Models\MembershipRenewal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MembershipRenewalService
{
    public function createRenewal(
        User $user,
        MembershipPackage $package,
        string $paymentMethod,
        string $status,
    ): MembershipRenewal {
        return DB::transaction(function () use ($user, $package, $paymentMethod, $status) {
            $current = $user->activeMembership;

            if (! $current) {
                $start = now();
                $current = Membership::query()->create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'status' => 'pending_verification',
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->addDays($package->duration_days)->toDateString(),
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'pending',
                ]);
                $previousEnd = $start->toDateString();
            } else {
                $previousEnd = $current->end_date->toDateString();
            }

            $newEnd = now()->parse($previousEnd)->addDays($package->duration_days)->toDateString();

            $renewal = MembershipRenewal::query()->create([
                'membership_id' => $current->id,
                'user_id' => $user->id,
                'package_id' => $package->id,
                'previous_end_date' => $previousEnd,
                'new_end_date' => $newEnd,
                'status' => $status,
                'payment_method' => $paymentMethod,
                'amount_paid' => $package->price,
            ]);

            if ($paymentMethod === 'midtrans') {
                $renewal->update([
                    'midtrans_order_id' => 'GYM-'.$renewal->id,
                ]);
            }

            return $renewal->fresh(['package', 'membership']);
        });
    }

    public function hasPendingMidtransRenewal(User $user, string $packageId): bool
    {
        return MembershipRenewal::query()
            ->where('user_id', $user->id)
            ->where('package_id', $packageId)
            ->where('status', 'pending_payment')
            ->exists();
    }
}
