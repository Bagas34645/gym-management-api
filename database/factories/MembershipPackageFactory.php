<?php

namespace Database\Factories;

use App\Models\MembershipPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPackage>
 */
class MembershipPackageFactory extends Factory
{
    protected $model = MembershipPackage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Package',
            'type' => 'monthly',
            'duration_days' => 30,
            'price' => fake()->randomFloat(2, 100000, 500000),
            'description' => fake()->sentence(),
            'benefits' => ['Gym access', 'Locker'],
            'status' => 'active',
        ];
    }
}
