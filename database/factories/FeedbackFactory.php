<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'category' => fake()->randomElement(['facility', 'trainer', 'service', 'cleanliness', 'other']),
            'message' => fake()->paragraph(),
            'is_anonymous' => false,
            'status' => 'new',
            'submitted_at' => now(),
        ];
    }
}
