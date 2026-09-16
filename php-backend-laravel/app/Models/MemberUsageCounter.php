<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Monthly consumption of a quota feature. Written only by MemberEntitlements. */
final class MemberUsageCounter extends Model
{
    protected $fillable = ['user_id', 'feature_key', 'period_start', 'used'];

    protected $casts = [
        'used' => 'integer',
    ];
}
