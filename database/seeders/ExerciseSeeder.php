<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = [
            ['name' => 'Bench Press', 'muscle_group' => 'Chest', 'difficulty_level' => 'intermediate'],
            ['name' => 'Squat', 'muscle_group' => 'Legs', 'difficulty_level' => 'intermediate'],
            ['name' => 'Deadlift', 'muscle_group' => 'Back', 'difficulty_level' => 'advanced'],
            ['name' => 'Pull Up', 'muscle_group' => 'Back', 'difficulty_level' => 'intermediate'],
            ['name' => 'Push Up', 'muscle_group' => 'Chest', 'difficulty_level' => 'beginner'],
            ['name' => 'Lunges', 'muscle_group' => 'Legs', 'difficulty_level' => 'beginner'],
            ['name' => 'Plank', 'muscle_group' => 'Core', 'difficulty_level' => 'beginner'],
            ['name' => 'Shoulder Press', 'muscle_group' => 'Shoulders', 'difficulty_level' => 'intermediate'],
            ['name' => 'Bicep Curl', 'muscle_group' => 'Arms', 'difficulty_level' => 'beginner'],
            ['name' => 'Tricep Dip', 'muscle_group' => 'Arms', 'difficulty_level' => 'intermediate'],
            ['name' => 'Leg Press', 'muscle_group' => 'Legs', 'difficulty_level' => 'beginner'],
            ['name' => 'Lat Pulldown', 'muscle_group' => 'Back', 'difficulty_level' => 'beginner'],
            ['name' => 'Romanian Deadlift', 'muscle_group' => 'Legs', 'difficulty_level' => 'advanced'],
            ['name' => 'Cable Fly', 'muscle_group' => 'Chest', 'difficulty_level' => 'intermediate'],
            ['name' => 'Face Pull', 'muscle_group' => 'Shoulders', 'difficulty_level' => 'beginner'],
            ['name' => 'Hip Thrust', 'muscle_group' => 'Legs', 'difficulty_level' => 'intermediate'],
            ['name' => 'Russian Twist', 'muscle_group' => 'Core', 'difficulty_level' => 'beginner'],
            ['name' => 'Calf Raise', 'muscle_group' => 'Legs', 'difficulty_level' => 'beginner'],
            ['name' => 'Incline Dumbbell Press', 'muscle_group' => 'Chest', 'difficulty_level' => 'intermediate'],
            ['name' => 'Rowing Machine', 'muscle_group' => 'Cardio', 'difficulty_level' => 'beginner'],
        ];

        foreach ($exercises as $exercise) {
            Exercise::query()->firstOrCreate(
                ['name' => $exercise['name']],
                array_merge($exercise, [
                    'id' => Str::uuid()->toString(),
                    'description' => 'Standard '.$exercise['name'].' exercise.',
                ])
            );
        }
    }
}
