<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'muscle_group',
        'difficulty_level',
        'video_url',
    ];

    public function workoutPlanExercises(): HasMany
    {
        return $this->hasMany(WorkoutPlanExercise::class);
    }
}
