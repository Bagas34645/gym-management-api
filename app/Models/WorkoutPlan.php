<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutPlan extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'trainer_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'goal',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function workoutLogs(): HasMany
    {
        return $this->hasMany(WorkoutLog::class);
    }

    public function workoutPlanExercises(): HasMany
    {
        return $this->hasMany(WorkoutPlanExercise::class);
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'workout_plan_exercises')
            ->withPivot(['order', 'sets', 'reps', 'weight_kg', 'rest_seconds', 'notes'])
            ->withTimestamps();
    }
}
