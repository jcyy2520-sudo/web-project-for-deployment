<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackSettings extends Model
{
    protected $table = 'feedback_settings';

    protected $fillable = [
        'rate_limit',
        'cooldown_days',
        'profanity_filter_enabled',
        'duplicate_detection_enabled',
        'profanity_list'
    ];

    protected $casts = [
        'rate_limit' => 'integer',
        'cooldown_days' => 'integer',
        'profanity_filter_enabled' => 'boolean',
        'duplicate_detection_enabled' => 'boolean',
        'profanity_list' => 'array'
    ];

    /**
     * Get or create default settings
     */
    public static function getSettings()
    {
        $s = self::first();
        if (!$s) {
            $s = self::create([
                'rate_limit' => 2,
                'cooldown_days' => 7,
                'profanity_filter_enabled' => true,
                'duplicate_detection_enabled' => true,
                'profanity_list' => json_encode(['shit','fuck','bitch','asshole','damn'])
            ]);
        }

        // Ensure profanity_list is returned as array
        if (is_string($s->profanity_list)) {
            $s->profanity_list = json_decode($s->profanity_list, true) ?? [];
        }

        return $s;
    }
}
