<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A batch of unique sponsor codes. Rules of type sponsor_code draw one per grant. */
final class RewardCodePool extends Model
{
    use AuditsAdminChanges;

    protected $fillable = ['sponsor_id', 'name', 'instructions', 'low_stock_threshold'];

    protected $casts = ['low_stock_threshold' => 'integer'];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(RewardSponsor::class, 'sponsor_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(RewardCode::class, 'pool_id');
    }

    public function remaining(): int
    {
        return $this->codes()->whereNull('grant_id')->count();
    }
}
