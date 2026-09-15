<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One de-duplicated ad impression or click — see {@see \App\Services\AdTracker}. */
final class AdEvent extends Model
{
    public const IMPRESSION = 'impression';
    public const CLICK = 'click';

    public const UPDATED_AT = null;

    protected $fillable = ['ad_id', 'kind', 'placement', 'surface', 'viewer_hash', 'user_id', 'match_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
