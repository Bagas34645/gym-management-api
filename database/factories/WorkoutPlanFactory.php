<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutPlan>
 */
class WorkoutPlanFactory extends Factory
{
    protected $model = WorkoutPlan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).' Plan',
            'description' => fake()->sentence(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeeks(4)->toDateString(),
            'goal' => fake()->randomElement(['Strength', 'Fat loss', 'Endurance']),
            'status' => 'active',
        ];
    }
}
