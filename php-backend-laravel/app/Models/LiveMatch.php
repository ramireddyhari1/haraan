<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class LiveMatch extends Model
{
    // Every save (incl. per-ball score updates) emits content.updated {domain: matches}
    // so the app's GameHub ActionBoard refreshes the live score in-place over Reverb.
    use BroadcastsContentChanges;

    protected string $contentDomain = 'matches';

    protected $guarded = [];

    /**
     * Delete cleanup. `player_match_stats` and `match_actions` cascade via FK, but
     * `match_xp_ledger` and `reputation_events` carry only a plain `match_id` index
     * — no FK, so they would be orphaned. The ledger especially MUST go: leaderboards
     * rank on it, so a deleted match's XP would keep counting (and a deleted cheated
     * match wouldn't undo the cheat). Runs for every delete path, admin or code.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $match): void {
            DB::transaction(function () use ($match): void {
                DB::table('match_xp_ledger')->where('match_id', $match->id)->delete();
                DB::table('reputation_events')->where('match_id', $match->id)->delete();
            });
        });
    }

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
     * The single source of truth for "who may see which match".
     *
     * Every PUBLIC match is visible to everyone, signed in or not (product
     * decision 2026-07-21): a guest browsing the ActionBoard sees the whole
     * public feed, not a teaser. Privacy is carried solely by `is_private`.
     *
     *   everyone (incl. guests) → every non-private match
     *   private                 → nobody here; creator / squad / join code only
     *
     * FEATURED no longer gates *visibility* — it is now purely a curation
     * signal driving ordering and which group a match renders in. `$scope`
     * still narrows intent for callers that want one slice.
     *
     * @param  string|null  $scope  'local' | 'featured' | 'all' | null (default: all public)
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer, ?string $scope = null): Builder
    {
        // Private matches never surface in any feed — they are reachable only by
        // join code or direct detail lookup (see isVisibleTo / scopeByJoinCode).
        $query->where('is_private', false);

        if ($scope === 'featured') {
            return $query->where('visibility', self::VIS_FEATURED);
        }

        if ($scope === 'local') {
            // An explicit district slice still needs an identity to resolve
            // "local to whom"; a guest has no district, so it yields nothing.
            if ($viewer === null) {
                return $query->whereRaw('1 = 0');
            }
            return $query->where('visibility', self::VIS_LOCAL)
                ->where('district', $viewer->district);
        }

        // Default: the entire public feed, for every viewer.
        return $query;
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
        // Any public match (LOCAL or FEATURED) is open to everyone, guests
        // included. Mirrors scopeVisibleTo: if it can appear in the feed it must
        // be reachable by id, or tapping a card 404s.
        return true;
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
