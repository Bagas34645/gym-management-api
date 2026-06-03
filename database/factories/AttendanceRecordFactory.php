<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('-7 days', 'now');

        return [
            'user_id' => User::factory(),
            'check_in_time' => $checkIn,
            'check_out_time' => (clone $checkIn)->modify('+'.fake()->numberBetween(45, 120).' minutes'),
            'location' => 'Main Entrance',
            'face_match_confidence' => fake()->randomFloat(2, 0.75, 0.99),
            'verification_status' => 'verified',
        ];
    }
}
