<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Org hierarchy: STATE > DISTRICT > AREA > VENUE > DEPARTMENT (self-referential
 * via parent_id). This is the future tenant key — domain records carry a
 * nullable organization_id, and admins are linked through user_organization_map.
 * Tenant query scoping is not enabled yet (Phase 1b groundwork only).
 */
final class OrganizationUnit extends Model
{
    use HasFactory;

    protected $table = 'organization_units';

    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Admins assigned to this unit via the user_organization_map pivot. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_organization_map', 'organization_id', 'user_id')
            ->withPivot(['designation', 'is_primary'])
            ->withTimestamps();
    }

    /** Users whose primary/home organization is this unit. */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class, 'organization_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'organization_id');
    }

    public function liveMatches(): HasMany
    {
        return $this->hasMany(LiveMatch::class, 'organization_id');
    }

    /**
     * This unit's id plus every descendant's, walking the parent_id tree. Used
     * by tenant scoping so a district manager also sees its areas/venues.
     *
     * @return array<int>
     */
    public function descendantAndSelfIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantAndSelfIds());
        }

        return $ids;
    }
}
