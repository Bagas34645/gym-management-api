<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceRegistration extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'face_embedding',
        'embedding_vector',
        'registered_at',
        'updated_at',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'updated_at' => 'datetime',
            'is_verified' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
