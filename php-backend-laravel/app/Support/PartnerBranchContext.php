<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Collection;

/**
 * Which branch the partner console is currently looking at.
 *
 * One selection, held in the session, read by the topbar switcher and by every
 * venue-lane dashboard widget. "All branches" is the absence of a selection
 * (null) rather than a magic id, so the default costs nothing and a brand-new
 * partner never has to choose before the console works.
 *
 * The selection is validated on EVERY read, not just when it is set. A branch
 * can be deactivated, reassigned, or taken off a desk person between one request
 * and the next, and a stale id must degrade to "all branches" rather than
 * throwing the owner out of their own console. Session state is a convenience;
 * {@see User::branches()} remains the boundary.
 */
final class PartnerBranchContext
{
    private const SESSION_KEY = 'partner.branch_id';

    /**
     * Memoised per user — the topbar and half a dozen widgets all ask on the same
     * page render.
     *
     * Keyed by user id rather than held as a single value, because a bare static
     * would hand one partner's branch list to the next request under a
     * long-running worker (Octane, queues, the test process) where statics
     * outlive the request. Keying it makes the cache incapable of the mix-up
     * rather than merely unlikely to hit it.
     *
     * @var array<int, Collection>
     */
    private static array $memo = [];

    /** Every branch the signed-in user may act on, ordered for display. */
    public static function branches(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return self::$memo[$user->id] ??= $user->branches()
            ->orderBy('branch_label')
            ->orderBy('name')
            ->get();
    }

    /**
     * The selected branch id, or null for "all branches".
     *
     * Returns null when the stored id is no longer one the user may reach, which
     * is why callers must treat null as "no filter" rather than an error.
     */
    public static function currentId(): ?int
    {
        $stored = session(self::SESSION_KEY);

        if (! is_int($stored) && ! (is_string($stored) && ctype_digit($stored))) {
            return null;
        }

        $stored = (int) $stored;

        return self::branches()->contains('id', $stored) ? $stored : null;
    }

    /** The selected branch, or null for "all branches". */
    public static function current(): ?Venue
    {
        $id = self::currentId();

        return $id === null ? null : self::branches()->firstWhere('id', $id);
    }

    /**
     * Store a selection. Anything the user may not reach clears it instead of
     * erroring — a tampered id is simply not a selection.
     */
    public static function select(?int $venueId): void
    {
        if ($venueId === null || ! self::branches()->contains('id', $venueId)) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session([self::SESSION_KEY => $venueId]);
    }

    /** What the switcher shows when closed. */
    public static function label(): string
    {
        return self::current()?->branchName() ?? 'All branches';
    }

    /**
     * Whether the switcher is worth rendering at all.
     *
     * A single-branch partner — which is every partner today — gets no control,
     * because a dropdown with one option is chrome that does nothing. It appears
     * on its own the day someone opens a second branch.
     */
    public static function isMultiBranch(): bool
    {
        return self::branches()->count() > 1;
    }

    /** Drop the memo (tests, and anything that mutates branches mid-request). */
    public static function flush(): void
    {
        self::$memo = [];
    }
}
