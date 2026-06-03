<?php

namespace Database\Seeders;

use App\Models\Trainer;
use App\Models\TrainerSchedule;
use Illuminate\Database\Seeder;

class TrainerScheduleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Trainer::query()->get() as $trainer) {
            foreach ([1, 3, 5] as $day) {
                TrainerSchedule::query()->firstOrCreate(
                    [
                        'trainer_id' => $trainer->id,
                        'day_of_week' => $day,
                    ],
                    [
                        'start_time' => '08:00:00',
                        'end_time' => '12:00:00',
                        'capacity' => 5,
                        'status' => 'active',
                    ]
                );
            }
        }
    }
}
