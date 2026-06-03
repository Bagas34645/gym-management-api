<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressWeight extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'progress_weight';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'weight_kg',
        'recorded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'recorded_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
