<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MessageSettings extends Model
{
    protected $table = 'message_settings';

    protected $fillable = [
        'user_message_limit',
        'last_updated_by',
    ];

    protected $casts = [
        'user_message_limit' => 'integer',
        'last_updated_by' => 'integer',
    ];

    /**
     * Get or create default message settings (singleton pattern)
     */
    public static function getSettings()
    {
        return Cache::remember('message_settings', 3600, function () {
            $settings = self::first();
            if (!$settings) {
                $settings = self::create([
                    'user_message_limit' => 2,
                ]);
            }
            return $settings;
        });
    }

    /**
     * Get the current user message limit
     */
    public static function getMessageLimit(): int
    {
        return self::getSettings()->user_message_limit;
    }

    /**
     * Relationship: the admin who last updated this setting
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
