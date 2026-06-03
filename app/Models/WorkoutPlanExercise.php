<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutPlanExercise extends Model
{
    protected $fillable = [
        'workout_plan_id',
        'exercise_id',
        'order',
        'sets',
        'reps',
        'weight_kg',
        'rest_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
        ];
    }

    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
