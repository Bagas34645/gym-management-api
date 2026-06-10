<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'face_image_path',
        'registered_at',
        'updated_at',
        'is_verified',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'updated_at' => 'datetime',
            'verified_at' => 'datetime',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * PostgreSQL returns the `bytea` column as a stream resource via PDO; read it
     * into a plain string so consumers (e.g. embedding decryption) get a string.
     */
    protected function faceEmbedding(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => is_resource($value) ? stream_get_contents($value) : $value,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
