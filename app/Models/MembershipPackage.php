<?php

namespace App\Models;

use Database\Factories\MembershipPackageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPackage extends Model
{
    /** @use HasFactory<MembershipPackageFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'type',
        'duration_days',
        'price',
        'description',
        'benefits',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'price' => 'decimal:2',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'package_id');
    }

    public function membershipRenewals(): HasMany
    {
        return $this->hasMany(MembershipRenewal::class, 'package_id');
    }
}
