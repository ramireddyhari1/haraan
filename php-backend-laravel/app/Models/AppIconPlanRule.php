<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one plan may do with one icon: pick it in the app, and/or have it put on the home
 * screen automatically.
 *
 * @property int $id
 * @property string $slot_key
 * @property int $plan_id
 * @property bool $can_pick
 * @property bool $is_auto
 */
final class AppIconPlanRule extends Model
{
    protected $fillable = ['slot_key', 'plan_id', 'can_pick', 'is_auto'];

    protected $casts = ['can_pick' => 'boolean', 'is_auto' => 'boolean'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MemberPlan::class, 'plan_id');
    }
}
