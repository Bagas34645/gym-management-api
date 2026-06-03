<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainerSchedule extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'trainer_id',
        'day_of_week',
        'start_time',
        'end_time',
        'capacity',
        'status',
    ];

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(TrainerBooking::class, 'schedule_id');
    }
}
