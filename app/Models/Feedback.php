<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'feedback';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'rating',
        'category',
        'message',
        'is_anonymous',
        'status',
        'admin_notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
