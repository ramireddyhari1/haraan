<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One launcher icon baked into the Android app (an activity-alias). Rows are created by
 * migration only, because new art needs an app release; everything else is the admin's.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property bool $is_active
 * @property int $sort
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 */
final class AppIconSlot extends Model
{
    /** The icon everyone always has, so a member can always go back. */
    public const DEFAULT = 'default';

    protected $fillable = ['key', 'name', 'is_active', 'sort', 'starts_at', 'ends_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(AppIconPlanRule::class, 'slot_key', 'key');
    }

    public function isDefault(): bool
    {
        return $this->key === self::DEFAULT;
    }

    /** Has a window at all. */
    public function isSeasonal(): bool
    {
        return $this->starts_at !== null || $this->ends_at !== null;
    }

    /** Switched on and inside its window. The default icon is always live. */
    public function isLive(CarbonInterface $now): bool
    {
        if ($this->isDefault()) {
            return true;
        }

        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->ends_at === null || $this->ends_at->gt($now));
    }

    public function previewUrl(): string
    {
        return asset('images/app-icons/' . $this->key . '.png');
    }
}
