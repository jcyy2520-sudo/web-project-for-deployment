<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageItem extends Model
{
    protected $fillable = [
        'section_id',
        'title',
        'description',
        'icon',
        'image_url',
        'step_number',
        'link',
        'metadata',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_visible' => 'boolean',
    ];

    public function section()
    {
        return $this->belongsTo(LandingPageSection::class, 'section_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}
