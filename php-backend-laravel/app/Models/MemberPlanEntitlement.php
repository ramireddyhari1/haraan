<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Membership\MemberEntitlements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One feature's value on one plan. `limit_value` null means unlimited; booleans ignore it.
 *
 * @property int $plan_id
 * @property string $feature_key
 * @property bool $enabled
 * @property int|null $limit_value
 */
final class MemberPlanEntitlement extends Model
{
    protected $fillable = ['plan_id', 'feature_key', 'enabled', 'limit_value'];

    protected $casts = [
        'enabled' => 'boolean',
        'limit_value' => 'integer',
    ];

    protected static function booted(): void
    {
        // A plan edit changes what every member on it has.
        $flush = fn () => MemberEntitlements::flush();
        self::saved($flush);
        self::deleted($flush);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MemberPlan::class, 'plan_id');
    }
}
