<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerBooking extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'trainer_id',
        'schedule_id',
        'session_date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'rating',
        'feedback',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'cancelled_at' => 'datetime',
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

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TrainerSchedule::class, 'schedule_id');
    }
}
