<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'profile_photo_url',
        'age',
        'height_cm',
        'weight_kg',
        'fitness_goal',
        'device_token',
        'last_login_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'weight_kg' => 'decimal:2',
        ];
    }

    public function trainer(): HasOne
    {
        return $this->hasOne(Trainer::class);
    }

    public function faceRegistration(): HasOne
    {
        return $this->hasOne(FaceRegistration::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function activeMembership(): HasOne
    {
        return $this->hasOne(Membership::class)->where('status', 'active');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function trainerBookings(): HasMany
    {
        return $this->hasMany(TrainerBooking::class);
    }

    public function workoutPlans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class);
    }

    public function progressWeights(): HasMany
    {
        return $this->hasMany(ProgressWeight::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'member_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', ['admin', 'super_admin']);
    }

    public function scopeMembers($query)
    {
        return $query->where('role', 'member');
    }
}
