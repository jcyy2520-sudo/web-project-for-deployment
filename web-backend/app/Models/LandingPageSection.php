<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageSection extends Model
{
    protected $fillable = [
        'section_key',
        'title',
        'subtitle',
        'description',
        'badge_text',
        'button_primary_text',
        'button_primary_link',
        'button_secondary_text',
        'button_secondary_link',
        'image_url',
        'image_alt',
        'metadata',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_visible' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(LandingPageItem::class, 'section_id')
            ->where('is_visible', true)
            ->orderBy('sort_order');
    }

    public function allItems()
    {
        return $this->hasMany(LandingPageItem::class, 'section_id')
            ->orderBy('sort_order');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('section_key', $key);
    }
}
