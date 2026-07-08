<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipRenewal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipRenewal>
 */
class MembershipRenewalFactory extends Factory
{
    protected $model = MembershipRenewal::class;

    public function definition(): array
    {
        $membership = Membership::factory()->create();

        return [
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'package_id' => $membership->package_id,
            'previous_end_date' => $membership->end_date,
            'new_end_date' => $membership->end_date->copy()->addDays(30),
            'status' => 'pending_verification',
            'payment_method' => 'cash',
            'amount_paid' => 100000,
        ];
    }
}
