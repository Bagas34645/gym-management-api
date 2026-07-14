<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\WorkoutPlan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WorkoutPlanExerciseSync
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function sync(WorkoutPlan $plan, array $items): void
    {
        $validator = Validator::make(
            ['exercises' => $items],
            [
                'exercises' => ['required', 'array', 'min:1'],
                'exercises.*.exercise_id' => ['nullable', 'uuid', 'exists:exercises,id'],
                'exercises.*.name' => ['required_without:exercises.*.exercise_id', 'string', 'max:255'],
                'exercises.*.order' => ['required', 'integer', 'min:1'],
                'exercises.*.sets' => ['required', 'integer', 'min:1'],
                'exercises.*.reps' => ['required', 'integer', 'min:1'],
                'exercises.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
                'exercises.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
                'exercises.*.notes' => ['nullable', 'string'],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $syncData = [];
        $seenExerciseIds = [];

        foreach ($items as $item) {
            if (! empty($item['exercise_id'])) {
                $exercise = Exercise::query()->findOrFail($item['exercise_id']);
            } else {
                $exercise = Exercise::query()->firstOrCreate(
                    ['name' => $item['name']],
                    [
                        'description' => 'Latihan kustom.',
                        'muscle_group' => 'General',
                        'difficulty_level' => 'beginner',
                    ]
                );
            }

            if (in_array($exercise->id, $seenExerciseIds, true)) {
                throw ValidationException::withMessages([
                    'exercises' => ['Latihan duplikat tidak diperbolehkan dalam satu program.'],
                ]);
            }

            $seenExerciseIds[] = $exercise->id;
            $syncData[$exercise->id] = [
                'order' => (int) $item['order'],
                'sets' => (int) $item['sets'],
                'reps' => (int) $item['reps'],
                'weight_kg' => $item['weight_kg'] ?? null,
                'rest_seconds' => $item['rest_seconds'] ?? null,
                'notes' => $item['notes'] ?? null,
            ];
        }

        $plan->exercises()->sync($syncData);
    }
}
