<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trainer extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'specialization',
        'experience_years',
        'certification',
        'bio',
        'hourly_rate',
        'average_rating',
        'total_sessions',
        'total_members',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'average_rating' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(TrainerSchedule::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(TrainerBooking::class);
    }

    public function workoutPlans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
