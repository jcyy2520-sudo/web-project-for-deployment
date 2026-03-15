<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
    ];

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($this->value) ? (float) $this->value : 0,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->typed_value : $default;
    }

    public static function setValue(string $key, $value, string $type = 'text', string $group = 'general', string $label = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => $type,
                'group' => $group,
                'label' => $label ?? $key,
            ]
        );
    }
}
