<?php

namespace App\Models;

use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'package_id',
        'status',
        'start_date',
        'end_date',
        'payment_method',
        'payment_status',
        'payment_proof_url',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MembershipPackage::class, 'package_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(MembershipRenewal::class);
    }

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', 'active')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }
}
