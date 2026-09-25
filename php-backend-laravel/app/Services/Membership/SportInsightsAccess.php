<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\LiveMatch;
use App\Models\MemberSportSelection;
use App\Models\User;
use App\Support\Membership\InsightSports;
use App\Support\Membership\MemberFeature;
use App\Support\Membership\MembershipSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Advanced insights, per sport.
 *
 * The number of sports comes from the member's plan through MemberEntitlements
 * (`insights.advanced_sports`): unlimited = every sport, a number = that many sports the
 * member chooses, 0/off = none. This class adds only what a plain limit can't: which sports
 * were chosen, and the rules for changing them.
 *
 * Every insights endpoint calls authorize() BEFORE building anything, so no model call, no
 * event replay and no after-response AI job runs for a viewer who isn't entitled.
 */
class SportInsightsAccess
{
    public function __construct(private readonly MemberEntitlements $entitlements) {}

    /**
     * @throws EntitlementDenied 403 upgrade_required | selection_required, with `sport`
     */
    public function authorize(?User $user, string $sport): void
    {
        $sport = InsightSports::normalize($sport);

        $this->entitlements->assertChosen(
            $user,
            MemberFeature::INSIGHTS_ADVANCED_SPORTS,
            $sport,
            $user === null ? [] : $this->selected($user)->pluck('sport')->all(),
            InsightSports::label($sport),
            ['sport' => $sport],
        );
    }

    public function authorizeMatch(?User $user, LiveMatch $match): void
    {
        $this->authorize($user, (string) $match->sport);
    }

    public function allows(?User $user, string $sport): bool
    {
        try {
            $this->authorize($user, $sport);

            return true;
        } catch (EntitlementDenied) {
            return false;
        }
    }

    /**
     * Where the member stands, shaped for the app's sport picker.
     *
     * @return array<string, mixed>
     */
    public function status(?User $user): array
    {
        $limit = $this->entitlements->limit($user, MemberFeature::INSIGHTS_ADVANCED_SPORTS);
        $mode = match (true) {
            $limit === null => 'all',
            $limit === 0 => 'none',
            default => 'choose',
        };

        $selections = $user === null ? collect() : $this->selected($user);
        $counting = $mode === 'choose' ? $selections->take($limit) : collect();
        $overLimit = $mode === 'choose' && $selections->count() > $limit;
        $cooldown = $this->cooldownDays();

        $sports = [];
        foreach (InsightSports::keys() as $key) {
            $row = $selections->firstWhere('sport', $key);
            $lockedUntil = $row !== null && ! $overLimit && $cooldown > 0
                ? $row->created_at->copy()->addDays($cooldown)
                : null;

            $sports[] = [
                'key' => $key,
                'label' => InsightSports::label($key),
                'selected' => $row !== null,
                'unlocked' => $mode === 'all' || $counting->contains('sport', $key),
                // When this sport may be swapped out; null once it can be.
                'locked_until' => $lockedUntil?->isFuture() ? $lockedUntil->toIso8601String() : null,
            ];
        }

        return [
            'mode' => $mode,
            'limit' => $limit,
            'plan' => ['code' => $this->entitlements->for($user)->plan->code, 'name' => $this->entitlements->for($user)->plan->name],
            'selected' => $selections->pluck('sport')->values()->all(),
            'slots_left' => $mode === 'choose' ? max(0, $limit - $selections->count()) : null,
            'over_limit' => $overLimit,
            'cooldown_days' => $cooldown,
            'sports' => $sports,
        ];
    }

    /**
     * Replace the member's chosen sports.
     *
     * Rules: only on a plan that asks the member to choose; at most `limit`; known sports
     * only; and a sport can't be swapped out until it has been held for the cooldown — without
     * that, a three-sport plan becomes every sport by re-picking before each match. Filling an
     * empty slot is never held back, and a member left over their limit (the plan's number was
     * lowered) may remove sports freely to get back under it.
     *
     * @param  list<mixed>  $sports
     * @return array<string, mixed> the new status
     *
     * @throws MembershipException 422
     * @throws EntitlementDenied 403 when the plan has no sports to choose
     */
    public function save(User $user, array $sports): array
    {
        $limit = $this->entitlements->limit($user, MemberFeature::INSIGHTS_ADVANCED_SPORTS);

        if ($limit === null) {
            throw new MembershipException('Your plan already includes advanced insights for every sport.', 422, 'all_sports_included');
        }

        if ($limit === 0) {
            $this->entitlements->assertChosen($user, MemberFeature::INSIGHTS_ADVANCED_SPORTS, '', [], 'a sport');
        }

        $wanted = [];
        foreach ($sports as $sport) {
            if (! is_string($sport) || ! InsightSports::exists($sport)) {
                throw new MembershipException('Pick sports from the list.', 422, 'unknown_sport');
            }
            $wanted[$sport] = true;
        }
        $wanted = array_keys($wanted);

        if (count($wanted) > $limit) {
            throw new MembershipException("You can choose up to {$limit} sports on your plan.", 422, 'too_many_sports');
        }

        $lock = Cache::lock('member-insight-sports:' . $user->id, 10);
        if (! $lock->get()) {
            throw new MembershipException('Your sports are already being saved.', 409, 'save_in_progress');
        }

        try {
            $current = $this->selected($user);
            $overLimit = $current->count() > $limit;
            $cooldown = $this->cooldownDays();
            $now = Carbon::now();

            $removing = $current->reject(fn (MemberSportSelection $s) => in_array($s->sport, $wanted, true));

            if (! $overLimit && $cooldown > 0) {
                $held = $removing->first(fn (MemberSportSelection $s) => $s->created_at->copy()->addDays($cooldown)->isFuture());
                if ($held !== null) {
                    $availableOn = $held->created_at->copy()->addDays($cooldown);
                    throw new MembershipException(
                        InsightSports::label($held->sport) . ' can be swapped out from ' . $availableOn->format('j M Y') . '.',
                        422,
                        'change_locked',
                    );
                }
            }

            DB::transaction(function () use ($user, $removing, $wanted, $current): void {
                if ($removing->isNotEmpty()) {
                    MemberSportSelection::query()->whereKey($removing->modelKeys())->delete();
                }

                foreach ($wanted as $sport) {
                    if (! $current->contains('sport', $sport)) {
                        MemberSportSelection::create(['user_id' => $user->id, 'sport' => $sport]);
                    }
                }
            });
        } finally {
            $lock->release();
        }

        return $this->status($user);
    }

    /** @return Collection<int, MemberSportSelection> oldest choice first */
    private function selected(User $user): Collection
    {
        return MemberSportSelection::query()
            ->where('user_id', $user->id)
            ->whereIn('sport', InsightSports::keys())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    private function cooldownDays(): int
    {
        return MembershipSettings::int('insight_sport_cooldown_days');
    }
}
