<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutLog extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'workout_plan_id',
        'logged_at',
        'exercises',
        'duration_minutes',
        'calories_burned',
        'mood',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'exercises' => 'array',
            'calories_burned' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }
}
