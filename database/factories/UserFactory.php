<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08'.fake()->unique()->numerify('##########'),
            'email_verified_at' => now(),
            'is_verified' => true,
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'member',
            'status' => 'active',
            'age' => fake()->numberBetween(18, 55),
            'height_cm' => fake()->numberBetween(155, 195),
            'weight_kg' => fake()->randomFloat(2, 50, 120),
            'fitness_goal' => fake()->randomElement(['weight_loss', 'muscle_gain', 'endurance', 'general_fitness']),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null, 'is_verified' => false]);
    }
}
