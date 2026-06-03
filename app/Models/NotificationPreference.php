<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'membership_reminder',
        'reminder_days_before',
        'promo_notification',
        'workout_reminder',
        'workout_reminder_time',
        'workout_reminder_days',
    ];

    protected function casts(): array
    {
        return [
            'membership_reminder' => 'boolean',
            'promo_notification' => 'boolean',
            'workout_reminder' => 'boolean',
            'workout_reminder_days' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
