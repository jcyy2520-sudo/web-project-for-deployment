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
        // 'password' excluded — set explicitly via $user->password = Hash::make(...) to prevent mass-assignment.
        // 'role' intentionally excluded from $fillable to prevent mass-assignment privilege escalation.
        // Use $user->role = 'value' explicitly when role changes are needed.
        'first_name',
        'last_name',
        'phone',
        'address',
        // 'is_active', 'account_status' excluded — set explicitly to prevent users from reactivating their own accounts.
        'account_status_reason',
        'profile_picture',
        'google_id',
        'profile_completed',
        'verification_method',
        'password_login_enabled'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'verification_code_expires_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'is_active' => 'boolean',
        'profile_completed' => 'boolean',
        'password_login_enabled' => 'boolean'
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

    public function accountAppeals()
    {
        return $this->hasMany(AccountAppeal::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }

    public function isCashier()
    {
        return $this->role === 'cashier';
    }

    public function isClient()
    {
        return $this->role === 'client';
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the full URL of the profile picture.
     * Appended to all JSON serializations so the frontend always gets a usable URL.
     */
    public function getProfilePictureUrlAttribute()
    {
        if ($this->profile_picture) {
            return asset('storage/' . $this->profile_picture);
        }
        return null;
    }

    protected $appends = ['profile_picture_url'];

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