<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Ad extends Model
{
    protected $fillable = [
        'sponsor', 'title', 'subtitle', 'image', 'logo', 'cta_text', 'cta_url',
        'placement', 'is_active', 'sort_order', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
