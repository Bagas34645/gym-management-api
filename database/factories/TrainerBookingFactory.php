<?php

namespace Database\Factories;

use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerBooking>
 */
class TrainerBookingFactory extends Factory
{
    protected $model = TrainerBooking::class;

    public function definition(): array
    {
        $trainer = Trainer::query()->inRandomOrder()->with('schedules')->first();

        return [
            'user_id' => User::factory(),
            'trainer_id' => $trainer?->id,
            'schedule_id' => $trainer?->schedules->first()?->id,
            'session_date' => now()->addDays(fake()->numberBetween(1, 7))->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'confirmed',
        ];
    }
}
