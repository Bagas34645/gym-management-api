<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'user_id' => User::factory(),
            'package_id' => MembershipPackage::factory(),
            'status' => 'active',
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+30 days'),
            'payment_method' => fake()->randomElement(['transfer', 'cash', 'qris']),
            'payment_status' => 'completed',
        ];
    }
}
