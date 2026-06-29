<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveMatch extends Model
{
    protected $guarded = [];

    /** Reach tiers. STATE is reserved for a later phase (column already exists). */
    public const VIS_LOCAL = 'LOCAL';
    public const VIS_FEATURED = 'FEATURED';

    protected $casts = [
        'probability' => 'array',
        'projected_score' => 'array',
        'batters' => 'array',
        'bowler' => 'array',
        'over_summary' => 'array',
        'timeline' => 'array',
        'home_squad' => 'array',
        'away_squad' => 'array',

        // ActionBoard ranking / verification
        'base_xp' => 'integer',
        'home_captain_confirmed' => 'boolean',
        'away_captain_confirmed' => 'boolean',
        'is_ranked' => 'boolean',
        'is_private' => 'boolean',
        'verification_deadline' => 'datetime',
        'verified_at' => 'datetime',
        'featured_at' => 'datetime',
    ];

    /** Owning organization unit (district/venue). Nullable; scoping not yet enabled. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_id');
    }

    /**
     * The single source of truth for "who may see which match". Authorization is
     * derived from the viewer (never trusted from the client); `$scope` only
     * narrows *intent* within what they're already allowed to see.
     *
     *   admin              → everything (optionally narrowed by scope)
     *   signed-in (dist D) → FEATURED + LOCAL matches in D
     *   guest              → FEATURED only
     *
     * @param  string|null  $scope  'local' | 'featured' | 'all' | null (default blend)
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer, ?string $scope = null): Builder
    {
        // Private matches never surface in any feed — they are reachable only by
        // join code or direct detail lookup (see isVisibleTo / scopeByJoinCode).
        $query->where('is_private', false);

        $isAdmin = $viewer?->isSuperAdmin() ?? false;
        $district = $viewer?->district;

        // Admins may explicitly ask for the god-view; otherwise admins still get
        // the same blend so their default feed isn't a firehose.
        if ($isAdmin && $scope === 'all') {
            return $query;
        }

        if ($scope === 'featured') {
            return $query->where('visibility', self::VIS_FEATURED);
        }

        if ($scope === 'local') {
            // Local needs an identity. Guests get nothing here (prompt to sign in).
            if ($viewer === null) {
                return $query->whereRaw('1 = 0');
            }
            return $query->where('visibility', self::VIS_LOCAL)
                ->where('district', $district);
        }

        // Default blend: featured for all, plus your own district's locals.
        if ($isAdmin) {
            return $query;
        }
        if ($viewer === null) {
            return $query->where('visibility', self::VIS_FEATURED);
        }
        return $query->where(function (Builder $w) use ($district): void {
            $w->where('visibility', self::VIS_FEATURED)
                ->orWhere(function (Builder $d) use ($district): void {
                    $d->where('visibility', self::VIS_LOCAL)->where('district', $district);
                });
        });
    }

    /** Find a match by its share/join code (private-match access path). */
    public function scopeByJoinCode(Builder $query, string $code): Builder
    {
        return $query->where('join_code', strtoupper(trim($code)));
    }

    /** Is this viewer one of the registered players in either squad? */
    public function hasSquadMember(?User $viewer): bool
    {
        if ($viewer === null || empty($viewer->player_id)) {
            return false;
        }
        $pid = (string) $viewer->player_id;
        foreach (array_merge($this->home_squad ?? [], $this->away_squad ?? []) as $p) {
            if (is_array($p) && (string) ($p['id'] ?? '') === $pid) {
                return true;
            }
        }
        return false;
    }

    /** True when this viewer is allowed to see this single match (for detail/show). */
    public function isVisibleTo(?User $viewer): bool
    {
        if ($viewer?->isSuperAdmin()) {
            return true;
        }
        // Private matches: only the creator or an added squad member may reach them
        // by id. Everyone else must use the join code (handled outside this check).
        if ($this->is_private) {
            return $viewer !== null
                && ((int) $this->user_id === (int) $viewer->id || $this->hasSquadMember($viewer));
        }
        if ((string) $this->visibility === self::VIS_FEATURED) {
            return true;
        }
        // LOCAL: same district, or the creator themselves.
        if ($viewer === null) {
            return false;
        }
        return (int) $this->user_id === (int) $viewer->id
            || ((string) $this->district !== '' && $this->district === $viewer->district);
    }

    /**
     * Count distinct registered players (squad entries with a non-null id)
     * on a given side. Guests (id === null) do not count toward Ranked.
     */
    public function distinctRegisteredPlayers(string $side): int
    {
        $squad = ($side === 'home' ? $this->home_squad : $this->away_squad) ?? [];
        $ids = [];
        foreach ($squad as $p) {
            $id = is_array($p) ? ($p['id'] ?? null) : null;
            if (!empty($id)) {
                $ids[$id] = true;
            }
        }
        return count($ids);
    }
}
