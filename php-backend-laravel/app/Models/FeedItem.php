<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class FeedItem extends Model
{
    protected $fillable = [
        'section', 'title', 'subtitle', 'image', 'badge', 'rating',
        'link_type', 'link_id', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
