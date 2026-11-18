<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\AppIconPlanRule;
use App\Models\AppIconSlot;
use App\Models\AppSetting;
use App\Models\MemberPlan;
use App\Models\User;
use App\Support\Membership\MemberFeature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Which home-screen icons a member (or guest) may wear, and which one goes on automatically.
 * All of it is set in /control → Finance → App icons; the app only applies it.
 */
final class AppIconCatalogue
{
    /** AppSetting key: the fewest hours between two automatic swaps on one phone. */
    public const MIN_HOURS_KEY = 'membership.app_icon_min_hours_between_auto';

    public const MIN_HOURS_DEFAULT = 72;

    public function __construct(private readonly MemberEntitlements $entitlements) {}

    public static function minHoursBetweenAuto(): int
    {
        $stored = AppSetting::get(self::MIN_HOURS_KEY);

        return is_numeric($stored) ? max(0, min(720, (int) $stored)) : self::MIN_HOURS_DEFAULT;
    }

    /**
     * The GET /api/membership/app-icons payload.
     *
     * - `icons`: every live icon some active plan can pick (plus the default), in order, with
     *   whether this caller may pick it and, if not, the cheapest plan that would let them.
     * - `auto`: the icon to put on the home screen automatically, or null to leave it to the
     *   member. A live seasonal auto icon beats a permanent one.
     *
     * @return array{auto: ?string, min_hours_between_auto: int, icons: list<array<string, mixed>>}
     */
    public function for(?User $user, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $plan = $this->entitlements->for($user)->plan;

        $slots = AppIconSlot::query()->orderBy('sort')->orderBy('id')->get()
            ->filter(fn (AppIconSlot $s): bool => $s->isLive($now));
        $rules = AppIconPlanRule::query()
            ->whereIn('plan_id', MemberPlan::query()->where('is_active', true)->pluck('id'))
            ->with('plan')
            ->get()
            ->groupBy('slot_key');

        $icons = [];
        $autos = [];
        foreach ($slots as $slot) {
            $slotRules = $rules->get($slot->key, collect());
            $mine = $slotRules->firstWhere('plan_id', $plan->id);
            $unlocked = $slot->isDefault() || (bool) $mine?->can_pick;

            $unlocking = $slotRules->where('can_pick', true)->pluck('plan')->filter()->sortBy('rank')->first();
            if (! $unlocked && $unlocking === null) {
                continue; // nobody can pick it, so it isn't on offer
            }

            $icons[] = [
                'key' => $slot->key,
                'name' => $slot->name,
                'unlocked' => $unlocked,
                'seasonal' => $slot->isSeasonal(),
                'unlocked_by' => $unlocked ? null : ['code' => $unlocking->code, 'name' => $unlocking->name],
            ];
            if ($unlocked && $mine?->is_auto) {
                $autos[] = $slot;
            }
        }

        $auto = collect($autos)->sortBy(fn (AppIconSlot $s): int => $s->isSeasonal() ? 0 : 1)->first();

        return [
            'auto' => $auto?->key,
            'min_hours_between_auto' => self::minHoursBetweenAuto(),
            'icons' => array_values($icons),
        ];
    }

    /**
     * Keep the older app.icon_pro / app.icon_hero features in step with the library, so app
     * builds from before the library (which only read those) follow /control too.
     */
    public static function syncLegacyFeatures(): void
    {
        $legacy = [MemberFeature::APP_ICON_PRO => 'pro', MemberFeature::APP_ICON_HERO => 'hero'];
        $now = Carbon::now();
        $live = AppIconSlot::query()->whereIn('key', $legacy)->get()->keyBy('key');

        foreach (MemberPlan::query()->pluck('id') as $planId) {
            foreach ($legacy as $feature => $slot) {
                $canPick = (bool) AppIconPlanRule::query()
                    ->where('plan_id', $planId)->where('slot_key', $slot)->value('can_pick');
                $enabled = $canPick && ($live->get($slot)?->isLive($now) ?? false);
                $exists = DB::table('member_plan_entitlements')
                    ->where('plan_id', $planId)->where('feature_key', $feature);
                if ($exists->exists()) {
                    $exists->update(['enabled' => $enabled, 'updated_at' => $now]);
                } else {
                    DB::table('member_plan_entitlements')->insert([
                        'plan_id' => $planId, 'feature_key' => $feature, 'enabled' => $enabled,
                        'limit_value' => null, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
        MemberEntitlements::flush();
    }
}
