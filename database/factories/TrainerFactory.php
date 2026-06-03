<?php

namespace Database\Factories;

use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trainer>
 */
class TrainerFactory extends Factory
{
    protected $model = Trainer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'specialization' => fake()->randomElement(['Strength', 'Cardio', 'HIIT', 'Yoga']),
            'experience_years' => fake()->numberBetween(1, 20),
            'certification' => 'Certified Personal Trainer',
            'bio' => fake()->paragraph(),
            'hourly_rate' => fake()->randomFloat(2, 75000, 350000),
            'status' => 'active',
        ];
    }
}
