<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'uuid',
        'username',
        'email',
        'password',
        'role',
        'first_name',
        'last_name',
        'phone',
        'address',
        'verification_code',
        'verification_code_expires_at',
        'is_active'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'verification_code_expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function staffAppointments()
    {
        return $this->hasMany(Appointment::class, 'staff_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationPreferences()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function recordedPayments()
    {
        return $this->hasMany(Payment::class, 'recorded_by');
    }

    public function completedAppointments()
    {
        return $this->hasMany(CompletionRecord::class, 'completed_by');
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }

    public function isClient()
    {
        return $this->role === 'client';
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function accessTokens()
    {
        return $this->hasMany(AccessToken::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = \Illuminate\Support\Str::uuid();
            }
        });
    }
}