<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use App\Models\Concerns\BroadcastsContentChanges;
use App\Models\Concerns\TargetsOrganizations;
use Illuminate\Database\Eloquent\Model;

/**
 * A runtime-toggleable app feature. Resolution is deterministic so a given user
 * always gets a stable answer for a percentage rollout (no flicker between
 * launches). See isEnabledFor() for the layered evaluation.
 */
final class FeatureFlag extends Model
{
    use AuditsAdminChanges;
    use BroadcastsContentChanges;
    use TargetsOrganizations;

    /** Clients refetch /api/config when a flag changes. */
    protected string $contentDomain = 'config';

    /**
     * The flags some code actually reads, and what each one changes. A flag not listed here
     * is still sent in /api/config, but no app version or server path looks at it, so
     * switching it changes nothing. Add a key here in the same change that starts reading it.
     */
    public const READ_BY = [
        'server_driven_home' => 'Android home uses Home layout',
        \App\Support\Membership\MembershipSettings::IN_APP_CHECKOUT_FLAG => 'Pro/Hero bought in the app',
    ];

    /** What reading this flag changes, or null when nothing reads it. */
    public function readBy(): ?string
    {
        return self::READ_BY[$this->key] ?? null;
    }

    protected $fillable = [
        'key',
        'name',
        'description',
        'enabled',
        'rollout_percentage',
        'organization_ids',
        'min_app_version',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'rollout_percentage' => 'integer',
        'organization_ids' => 'array',
    ];

    /**
     * Resolve this flag for a (possibly null/anonymous) user and optional app
     * version. Layers, all of which must pass:
     *   1. master `enabled`
     *   2. `min_app_version` (when both it and $appVersion are present)
     *   3. org targeting — if organization_ids is set, the user's home org must
     *      fall within one of the targeted org subtrees
     *   4. `rollout_percentage` — deterministic per-user bucketing
     */
    public function isEnabledFor(?User $user, ?string $appVersion = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if ($this->min_app_version !== null && $appVersion !== null
            && version_compare($appVersion, $this->min_app_version, '<')) {
            return false;
        }

        if (! $this->matchesOrganization($user)) {
            return false;
        }

        return $this->matchesRollout($user);
    }

    private function matchesRollout(?User $user): bool
    {
        $pct = max(0, min(100, $this->rollout_percentage));
        if ($pct >= 100) {
            return true;
        }
        if ($pct <= 0) {
            return false;
        }

        // Anonymous users can't be stably bucketed, so they only get full rollouts.
        if ($user === null) {
            return false;
        }

        $bucket = crc32($this->key.':'.$user->id) % 100;

        return $bucket < $pct;
    }
}
