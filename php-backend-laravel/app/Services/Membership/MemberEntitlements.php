<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\MemberEntitlementOverride;
use App\Models\MemberFeatureDefinition;
use App\Models\MemberPlan;
use App\Models\MemberSubscription;
use App\Models\MemberUsageCounter;
use App\Models\User;
use App\Support\Membership\MemberFeature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single answer to "may this member do that, and how much of it?".
 *
 * Every member gate in the codebase calls this class with a MemberFeature key. Nothing
 * outside it knows which plan codes exist or what they include — that lives in the database
 * and is edited in /control. Partner plans are a different system (PlanEntitlements) and are
 * never consulted here.
 *
 * Resolution: guest or no granting subscription → the default plan. Otherwise the highest
 * ranked subscription whose grantsAccess() is true. Then unexpired per-member overrides
 * replace the plan's value for their key.
 *
 * Bound as a scoped singleton, so the memo lives for one request or one queued job.
 */
final class MemberEntitlements
{
    /** @var array<string, EntitlementSet> */
    private array $memo = [];

    /** The default plan, loaded once per request — a list of members would otherwise re-read it per row. */
    private ?MemberPlan $defaultPlan = null;

    /** Drop memoised entitlements for one member, or for everyone. */
    public static function flush(?int $userId = null): void
    {
        if (! app()->resolved(self::class)) {
            return;
        }

        app(self::class)->forget($userId);
    }

    public function forget(?int $userId = null): void
    {
        if ($userId === null) {
            $this->memo = [];
            $this->defaultPlan = null;

            return;
        }

        unset($this->memo['u' . $userId]);
    }

    public function for(?User $user): EntitlementSet
    {
        $memoKey = $user === null ? 'guest' : 'u' . $user->id;

        return $this->memo[$memoKey] ??= $this->resolve($user);
    }

    public function allows(?User $user, string $key): bool
    {
        return $this->for($user)->allows($key);
    }

    public function limit(?User $user, string $key): ?int
    {
        return $this->for($user)->limit($key);
    }

    /** The subscription currently granting the member's plan, if any. */
    public function activeSubscription(?User $user): ?MemberSubscription
    {
        return $this->for($user)->subscription;
    }

    /**
     * Boolean gate.
     *
     * @throws EntitlementDenied
     */
    public function authorize(?User $user, string $key): void
    {
        $set = $this->for($user);

        if ($set->allows($key)) {
            return;
        }

        throw $this->deny($set, $key, EntitlementDenied::CODE_UPGRADE_REQUIRED);
    }

    /**
     * Ceiling gate for a feature counted from its own table: may the member go from
     * [$current] to [$current + $adding]?
     *
     * @throws EntitlementDenied
     */
    public function assertWithinLimit(?User $user, string $key, int $current, int $adding = 1): void
    {
        $set = $this->for($user);
        $limit = $set->limit($key);

        if ($limit === null || $current + $adding <= $limit) {
            return;
        }

        throw $this->deny($set, $key, $limit === 0
            ? EntitlementDenied::CODE_UPGRADE_REQUIRED
            : EntitlementDenied::CODE_LIMIT_REACHED, $limit, $current);
    }

    /** How much of a monthly quota the member has used this period. */
    public function usage(User $user, string $key): int
    {
        return (int) MemberUsageCounter::query()
            ->where('user_id', $user->id)
            ->where('feature_key', $key)
            ->where('period_start', $this->periodStart())
            ->value('used');
    }

    /** When the current quota period ends. */
    public function periodEndsAt(): Carbon
    {
        return Carbon::now()->startOfMonth()->addMonth();
    }

    /**
     * Use [$amount] of a monthly quota, atomically.
     *
     * The increment is a single conditional UPDATE — `used + n <= limit` is checked by the
     * database in the same statement that writes it — so two simultaneous taps can't both
     * squeeze under the ceiling, on any driver, without relying on row locks.
     *
     * @throws EntitlementDenied
     */
    public function consume(User $user, string $key, int $amount = 1): void
    {
        if (MemberFeature::type($key) !== MemberFeature::TYPE_QUOTA) {
            throw new \InvalidArgumentException("[{$key}] is not a quota feature.");
        }

        $set = $this->for($user);
        $limit = $set->limit($key);
        $period = $this->periodStart();

        if ($limit === 0) {
            throw $this->deny($set, $key, EntitlementDenied::CODE_UPGRADE_REQUIRED, 0, $this->usage($user, $key));
        }

        $now = Carbon::now();
        DB::table('member_usage_counters')->insertOrIgnore([
            'user_id' => $user->id, 'feature_key' => $key, 'period_start' => $period,
            'used' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $query = DB::table('member_usage_counters')
            ->where('user_id', $user->id)
            ->where('feature_key', $key)
            ->where('period_start', $period);

        if ($limit !== null) {
            $query->where('used', '<=', $limit - $amount);
        }

        $updated = $query->update([
            'used' => DB::raw('used + ' . (int) $amount),
            'updated_at' => $now,
        ]);

        if ($updated === 0) {
            throw $this->deny($set, $key, EntitlementDenied::CODE_LIMIT_REACHED, $limit, $this->usage($user, $key));
        }
    }

    /** Give back quota consumed for work that then failed to start. Never goes below zero. */
    public function release(User $user, string $key, int $amount = 1): void
    {
        DB::table('member_usage_counters')
            ->where('user_id', $user->id)
            ->where('feature_key', $key)
            ->where('period_start', $this->periodStart())
            ->where('used', '>=', $amount)
            ->update(['used' => DB::raw('used - ' . (int) $amount), 'updated_at' => Carbon::now()]);
    }

    /**
     * The entitlements a member has, shaped for the API: every visible feature with its
     * resolved value and, for quotas, what's been used.
     *
     * @return list<array<string, mixed>>
     */
    public function describe(?User $user): array
    {
        $set = $this->for($user);
        $definitions = MemberFeatureDefinition::query()->orderBy('sort')->get()->keyBy('key');
        $rows = [];

        foreach (MemberFeature::keys() as $key) {
            $definition = $definitions->get($key);
            if ($definition !== null && ! $definition->is_visible) {
                continue;
            }

            $type = MemberFeature::type($key);
            $row = [
                'key' => $key,
                'name' => $definition?->name ?? MemberFeature::label($key),
                'description' => $definition?->description,
                'type' => $type,
                'unit' => $definition?->unit,
                'enabled' => $set->allows($key),
                'limit' => $type === MemberFeature::TYPE_BOOLEAN ? null : $set->limit($key),
                'unlimited' => $type !== MemberFeature::TYPE_BOOLEAN && $set->allows($key) && $set->limit($key) === null,
            ];

            if ($type === MemberFeature::TYPE_QUOTA && $user !== null) {
                $row['used'] = $this->usage($user, $key);
                $row['resets_at'] = $this->periodEndsAt()->toIso8601String();
            }

            $rows[] = $row + ['sort' => $definition?->sort ?? 999];
        }

        usort($rows, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return array_map(function (array $r): array {
            unset($r['sort']);

            return $r;
        }, $rows);
    }

    private function resolve(?User $user): EntitlementSet
    {
        $subscription = $user === null ? null : $this->grantingSubscription($user);

        $plan = $subscription?->plan;
        if ($plan === null) {
            $subscription = null;
            $plan = $this->defaultPlan ??= MemberPlan::default();
        } else {
            $plan->loadMissing('entitlements');
        }

        $values = [];
        foreach ($plan->entitlements as $entitlement) {
            if (! MemberFeature::exists($entitlement->feature_key)) {
                continue;
            }
            $values[$entitlement->feature_key] = [
                'enabled' => $entitlement->enabled,
                'limit' => $entitlement->limit_value,
                'source' => 'plan',
            ];
        }

        if ($user !== null) {
            $overrides = MemberEntitlementOverride::query()
                ->where('user_id', $user->id)
                ->inEffect()
                ->orderBy('id')
                ->get();

            foreach ($overrides as $override) {
                if (! MemberFeature::exists($override->feature_key)) {
                    continue;
                }
                $values[$override->feature_key] = [
                    'enabled' => $override->enabled,
                    'limit' => $override->limit_value,
                    'source' => 'override',
                ];
            }
        }

        return new EntitlementSet($plan, $subscription, $values);
    }

    private function grantingSubscription(User $user): ?MemberSubscription
    {
        $now = Carbon::now();

        return MemberSubscription::query()
            ->with(['plan.entitlements', 'price'])
            ->where('user_id', $user->id)
            ->candidates()
            ->get()
            ->filter(fn (MemberSubscription $s) => $s->plan !== null && $s->grantsAccess($now))
            ->sort(fn (MemberSubscription $a, MemberSubscription $b) => [$b->plan->rank, $b->id] <=> [$a->plan->rank, $a->id])
            ->first();
    }

    /**
     * Gate for a limit feature that the member spends by CHOOSING things (e.g. the sports
     * advanced insights cover). Unlimited allows any choice; 0/off allows none; otherwise
     * [$choice] must be among the first `limit` of [$choices] — ordered oldest first, so a
     * lowered limit keeps the member's longest-held choices.
     *
     * @param  list<string>  $choices
     * @param  array<string, mixed>  $context
     *
     * @throws EntitlementDenied
     */
    public function assertChosen(?User $user, string $key, string $choice, array $choices, string $choiceLabel, array $context = []): void
    {
        $set = $this->for($user);
        $limit = $set->limit($key);

        if ($limit === null) {
            return;
        }

        if ($limit === 0) {
            throw $this->deny($set, $key, EntitlementDenied::CODE_UPGRADE_REQUIRED, 0, count($choices), $context);
        }

        if (in_array($choice, array_slice($choices, 0, $limit), true)) {
            return;
        }

        $upgrade = $this->cheapestPlanImproving($set, $key);
        $used = min(count($choices), $limit);
        $message = $used < $limit
            ? "Choose {$choiceLabel} as one of your {$limit} sports to see this."
            : "{$choiceLabel} isn't one of your {$limit} sports." . ($upgrade !== null ? " {$upgrade->name} includes every sport." : '');

        throw new EntitlementDenied($message, EntitlementDenied::CODE_SELECTION_REQUIRED, $key, $set->plan->code, $upgrade?->code, $limit, $used, $context);
    }

    /** @param array<string, mixed> $context */
    private function deny(EntitlementSet $set, string $key, string $reason, ?int $limit = null, ?int $used = null, array $context = []): EntitlementDenied
    {
        $upgrade = $this->cheapestPlanImproving($set, $key);
        $label = MemberFeature::label($key);

        $message = match (true) {
            $reason === EntitlementDenied::CODE_LIMIT_REACHED && $upgrade !== null
                => "You've reached your {$set->plan->name} limit for {$label}. {$upgrade->name} gives you more.",
            $reason === EntitlementDenied::CODE_LIMIT_REACHED
                => "You've reached the limit for {$label} on your plan.",
            $upgrade !== null => "{$label} is part of {$upgrade->name}.",
            default => "{$label} isn't available on your plan.",
        };

        return new EntitlementDenied($message, $reason, $key, $set->plan->code, $upgrade?->code, $limit, $used, $context);
    }

    /** The lowest-ranked sellable plan above the member's that would do better for [$key]. */
    private function cheapestPlanImproving(EntitlementSet $set, string $key): ?MemberPlan
    {
        $current = $set->limit($key);
        $isBoolean = MemberFeature::isBoolean($key);

        return MemberPlan::query()
            ->with('entitlements')
            ->where('is_active', true)
            ->where('rank', '>', $set->plan->rank)
            ->orderBy('rank')
            ->get()
            ->first(function (MemberPlan $plan) use ($key, $current, $isBoolean): bool {
                $row = $plan->entitlements->firstWhere('feature_key', $key);
                if ($row === null || ! $row->enabled) {
                    return false;
                }
                if ($isBoolean) {
                    return true;
                }

                return $row->limit_value === null || ($current !== null && $row->limit_value > $current);
            });
    }

    private function periodStart(): string
    {
        return Carbon::now()->startOfMonth()->toDateString();
    }
}
