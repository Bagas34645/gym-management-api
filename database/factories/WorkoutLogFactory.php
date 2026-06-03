<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutLog>
 */
class WorkoutLogFactory extends Factory
{
    protected $model = WorkoutLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'logged_at' => fake()->dateTimeBetween('-14 days', 'now'),
            'exercises' => [
                ['exercise_name' => 'Squat', 'sets' => 3, 'reps' => 10, 'weight_kg' => 60],
                ['exercise_name' => 'Bench Press', 'sets' => 3, 'reps' => 8, 'weight_kg' => 50],
            ],
            'duration_minutes' => fake()->numberBetween(30, 90),
            'calories_burned' => fake()->randomFloat(2, 200, 600),
            'mood' => fake()->randomElement(['great', 'good', 'tired']),
        ];
    }
}
