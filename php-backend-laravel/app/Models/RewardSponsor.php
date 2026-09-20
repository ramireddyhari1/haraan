<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A brand that funds post-match rewards (a payments app, a food brand, a sports brand, an OTT
 * service). Holds only public profile details — never an API key; any sponsor integration
 * reads its credentials from the server environment.
 */
final class RewardSponsor extends Model
{
    use AuditsAdminChanges;

    public const CATEGORIES = [
        'payments' => 'Payments',
        'food' => 'Food & delivery',
        'sports_brand' => 'Sports brand',
        'ott' => 'OTT / streaming',
        'other' => 'Other',
    ];

    protected $fillable = [
        'name', 'category', 'logo', 'brand_color', 'website_url', 'contact_name', 'contact_email', 'notes', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function programs(): HasMany
    {
        return $this->hasMany(RewardProgram::class, 'sponsor_id');
    }

    public function pools(): HasMany
    {
        return $this->hasMany(RewardCodePool::class, 'sponsor_id');
    }
}
