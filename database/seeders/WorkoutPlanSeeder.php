<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\Trainer;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutPlanExercise;
use Illuminate\Database\Seeder;

class WorkoutPlanSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = Exercise::query()->limit(5)->get();
        $members = User::query()
            ->where('role', 'member')
            ->whereDoesntHave('trainer')
            ->whereHas('memberships', fn ($q) => $q->where('status', 'active'))
            ->limit(5)
            ->get();

        foreach ($members as $member) {
            $trainer = Trainer::query()->inRandomOrder()->first();

            $plan = WorkoutPlan::query()->create([
                'user_id' => $member->id,
                'trainer_id' => $trainer?->id,
                'name' => 'Starter Plan - '.$member->name,
                'description' => '4-week strength and conditioning program',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addWeeks(4)->toDateString(),
                'goal' => 'Build strength and improve endurance',
                'status' => 'active',
            ]);

            foreach ($exercises as $order => $exercise) {
                WorkoutPlanExercise::query()->create([
                    'workout_plan_id' => $plan->id,
                    'exercise_id' => $exercise->id,
                    'order' => $order + 1,
                    'sets' => 3,
                    'reps' => 12,
                    'weight_kg' => 20,
                    'rest_seconds' => 60,
                ]);
            }
        }

    }
}
