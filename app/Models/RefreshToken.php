<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'token', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeValid($query)
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function scopeActive($query)
    {
        return $this->scopeValid($query);
    }
}
