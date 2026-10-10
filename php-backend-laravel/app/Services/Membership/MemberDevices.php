<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\AdminAction;
use App\Models\MemberDevice;
use App\Models\MemberPlan;
use App\Models\User;
use App\Support\Membership\MemberFeature;
use App\Support\Membership\MembershipSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Signed-in devices and the plan's limit on them (Free 1, Pro 1, Hero 3 — edited in /control).
 *
 * Every member sign-in on the app or the website becomes a MemberDevice. A new device that
 * finds no free slot is PENDING: it is signed in, but every request except the device
 * chooser's is refused until the member signs out somewhere else or upgrades. Pending rows
 * never count, so signing in on a new phone never locks the phone already in use.
 *
 * When a plan shrinks (Hero → Pro), the most recently used devices keep their slots and the
 * rest drop to pending — on whichever of them is opened next, the member sees the chooser.
 *
 * The partner app, the partner console and /control never enroll: their sign-ins carry no
 * member-client header and their routes skip the web check, so staff are never limited here.
 */
final class MemberDevices
{
    /** Header the member app sends on sign-in. Absent = not a member-app sign-in. */
    public const CLIENT_HEADER = 'X-Haraan-Client';

    public const CLIENT_ANDROID = 'member-android';

    public const CLIENT_IOS = 'member-ios';

    public const DECISION_OK = 'ok';

    public const DECISION_LIMIT = 'limit';

    /** last_active_at is written at most this often per device. */
    private const TOUCH_THROTTLE_SECONDS = 300;

    public function __construct(private readonly MemberEntitlements $entitlements) {}

    /**
     * Routes a device held at the chooser may still call: the chooser itself, sign-out, who am I,
     * and the plans (so "Upgrade to Hero" works from there).
     */
    public static function exemptFromLimit(Request $request): bool
    {
        return $request->is('api/account/devices', 'api/account/devices/*', 'api/auth/logout', 'api/auth/me', 'api/membership', 'api/membership/*');
    }

    public static function isMemberAppRequest(?Request $request): bool
    {
        return $request !== null
            && in_array((string) $request->header(self::CLIENT_HEADER), [self::CLIENT_ANDROID, self::CLIENT_IOS], true);
    }

    /**
     * Record a member-app sign-in. Re-signing in on the same install reuses its row, so it
     * keeps its slot instead of needing a second one.
     */
    public function enrollApp(User $user, Request $request, int $ttlSeconds): MemberDevice
    {
        $platform = (string) $request->header(self::CLIENT_HEADER) === self::CLIENT_IOS ? 'ios' : 'android';
        $name = $this->clean((string) $request->header('X-Device-Name'), 120)
            ?? ($platform === 'ios' ? 'iPhone' : 'Android phone');

        return $this->enroll($user, [
            'surface' => MemberDevice::SURFACE_APP,
            'platform' => $platform,
            'install_id' => $this->clean((string) $request->header('X-Device-Id'), 80),
            'name' => $name,
            'app_version' => $this->clean((string) $request->header('X-App-Version'), 32),
            'ip_address' => $request->ip(),
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);
    }

    /** Record a website sign-in. [$browserId] is the long-lived cookie that names this browser. */
    public function enrollWeb(User $user, Request $request, string $browserId): MemberDevice
    {
        return $this->enroll($user, [
            'surface' => MemberDevice::SURFACE_WEB,
            'platform' => 'web',
            'install_id' => $browserId,
            'name' => self::describeBrowser((string) $request->userAgent()),
            'app_version' => null,
            'ip_address' => $request->ip(),
            'expires_at' => null,
        ]);
    }

    /**
     * May this device be used right now? Promotes a pending device when a slot is free and
     * demotes the least recently used ones when the plan now allows fewer than are active.
     */
    public function evaluate(MemberDevice $device): string
    {
        if ($device->isRevoked()) {
            return self::DECISION_LIMIT;
        }

        $limit = $this->limitFor($device->user ?? User::query()->findOrFail($device->user_id));

        if (! MembershipSettings::deviceLimitsEnforced() || $limit === null) {
            $this->promote($device);

            return self::DECISION_OK;
        }

        $active = $this->liveQuery($device->user_id)
            ->where('status', MemberDevice::STATUS_ACTIVE)
            ->orderByDesc('last_active_at')->orderByDesc('id')
            ->get(['id', 'last_active_at']);

        if ($device->isPending()) {
            if ($active->count() < $limit) {
                $this->promote($device);

                return self::DECISION_OK;
            }

            return self::DECISION_LIMIT;
        }

        if ($active->count() <= $limit) {
            return self::DECISION_OK;
        }

        // The plan shrank. Keep the most recently used, hold the rest at the chooser.
        $keep = $active->take($limit)->pluck('id');
        MemberDevice::query()->whereIn('id', $active->pluck('id')->diff($keep)->all())
            ->update(['status' => MemberDevice::STATUS_PENDING]);

        if ($keep->contains($device->id)) {
            return self::DECISION_OK;
        }

        $device->status = MemberDevice::STATUS_PENDING;

        return self::DECISION_LIMIT;
    }

    /** The member's device limit: null = unlimited, never below 1. */
    public function limitFor(User $user): ?int
    {
        $limit = $this->entitlements->limit($user, MemberFeature::ACCOUNT_DEVICES);

        return $limit === null ? null : max(1, $limit);
    }

    /** Keep last_active_at fresh enough for idle expiry and the "last active" label. */
    public function touch(MemberDevice $device, ?string $ip = null): void
    {
        if ($device->last_active_at !== null && $device->last_active_at->gt(now()->subSeconds(self::TOUCH_THROTTLE_SECONDS))) {
            return;
        }

        $device->last_active_at = now();
        if ($ip !== null) {
            $device->ip_address = $ip;
        }
        $device->saveQuietly();
    }

    /** True when the device has gone stale on its own (token expiry or idle window). */
    public function hasLapsed(MemberDevice $device): bool
    {
        if ($device->expires_at !== null && $device->expires_at->isPast()) {
            return true;
        }

        return $device->last_active_at->lt(now()->subDays(MembershipSettings::int('device_idle_days')));
    }

    public function revoke(MemberDevice $device, string $reason, ?User $by = null): void
    {
        if ($device->isRevoked()) {
            return;
        }

        $device->forceFill([
            'status' => MemberDevice::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoked_reason' => $reason,
            'revoked_by' => $by?->id,
        ])->save();
    }

    /** Sign a member out everywhere, used by /control. Also kills legacy tokens that carry no device. */
    public function revokeAll(User $user, string $reason, ?User $by = null): int
    {
        $count = MemberDevice::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', MemberDevice::STATUS_REVOKED)
            ->update([
                'status' => MemberDevice::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_reason' => $reason,
                'revoked_by' => $by?->id,
            ]);

        \App\Support\JwtService::revokeAllFor($user);

        return $count;
    }

    /** Admin sign-out of one device, audit-logged. */
    public function revokeByAdmin(MemberDevice $device, User $admin): void
    {
        $this->revoke($device, MemberDevice::REASON_ADMIN, $admin);
        AdminAction::log('member_device.revoked', [
            'device_id' => $device->id,
            'name' => $device->name,
            'platform' => $device->platform,
        ], $device->user);
    }

    public function findLive(User $user, string $publicId): ?MemberDevice
    {
        return $this->liveQuery($user->id)->where('public_id', $publicId)->first();
    }

    /**
     * Everything the device chooser and the "Signed-in devices" screens show.
     *
     * @return array<string, mixed>
     */
    public function state(User $user, ?MemberDevice $current): array
    {
        $decision = $current === null ? self::DECISION_OK : $this->evaluate($current);
        $limit = $this->limitFor($user);
        $enforced = MembershipSettings::deviceLimitsEnforced();
        $plan = $this->entitlements->for($user)->plan;

        $devices = $this->liveQuery($user->id)->orderByDesc('last_active_at')->orderByDesc('id')->get();
        $active = $devices->where('status', MemberDevice::STATUS_ACTIVE)->count();

        $rows = $devices
            ->sortBy(fn (MemberDevice $d): int => $current !== null && $d->id === $current->id ? 0 : ($d->status === MemberDevice::STATUS_ACTIVE ? 1 : 2))
            ->values()
            ->map(fn (MemberDevice $d): array => [
                'id' => $d->public_id,
                'name' => $d->name,
                'platform' => $d->platform,
                'surface' => $d->surface,
                'status' => $d->status,
                'this_device' => $current !== null && $d->id === $current->id,
                'app_version' => $d->app_version,
                'signed_in_at' => $d->signed_in_at->toIso8601String(),
                'last_active_at' => $d->last_active_at->toIso8601String(),
                'last_active_label' => $current !== null && $d->id === $current->id ? 'This device' : 'Active '.$d->last_active_at->diffForHumans(),
            ])->all();

        $limitLabel = $limit === null ? 'unlimited devices' : $limit.' '.Str::plural('device', $limit);

        return [
            'enforced' => $enforced,
            'blocked' => $decision === self::DECISION_LIMIT,
            'this_device_id' => $current?->public_id,
            'limit' => $limit,
            'limit_label' => $limitLabel,
            'used' => $active,
            'plan' => ['code' => $plan->code, 'name' => $plan->name],
            'upgrade' => $this->upgradeFor($plan, $limit),
            'title' => MembershipSettings::text('device_limit_title'),
            'message' => str_replace(':limit', $limitLabel, MembershipSettings::text('device_limit_body')),
            'devices' => $rows,
        ];
    }

    /**
     * "Chrome on Windows", "Safari on iPhone". Deliberately coarse: enough for a member to
     * recognise their own browser, nothing that fingerprints it.
     */
    public static function describeBrowser(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'CrOS') => 'Chromebook',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        return $os === null ? $browser : "{$browser} on {$os}";
    }

    /** @param array<string, mixed> $attributes */
    private function enroll(User $user, array $attributes): MemberDevice
    {
        $now = now();

        $device = $attributes['install_id'] === null ? null : MemberDevice::query()
            ->where('user_id', $user->id)
            ->where('surface', $attributes['surface'])
            ->where('install_id', $attributes['install_id'])
            ->where('status', '!=', MemberDevice::STATUS_REVOKED)
            ->latest('id')
            ->first();

        if ($device !== null) {
            // A row that had lapsed gave up its slot; coming back, it queues like a new device
            // rather than bumping whichever device took the slot in the meantime.
            $status = $this->hasLapsed($device) ? MemberDevice::STATUS_PENDING : $device->status;
            $device->forceFill($attributes + [
                'status' => $status,
                'signed_in_at' => $now,
                'last_active_at' => $now,
            ])->save();
        } else {
            $device = MemberDevice::query()->create($attributes + [
                'user_id' => $user->id,
                'public_id' => (string) Str::ulid(),
                'status' => MemberDevice::STATUS_PENDING,
                'signed_in_at' => $now,
                'last_active_at' => $now,
            ]);
        }

        $device->setRelation('user', $user);
        $this->evaluate($device);

        return $device;
    }

    private function promote(MemberDevice $device): void
    {
        if ($device->isPending()) {
            $device->status = MemberDevice::STATUS_ACTIVE;
            $device->save();
        }
    }

    /** @return \Illuminate\Database\Eloquent\Builder<MemberDevice> */
    private function liveQuery(int $userId): \Illuminate\Database\Eloquent\Builder
    {
        return MemberDevice::query()->where('user_id', $userId)->live(MembershipSettings::int('device_idle_days'));
    }

    /**
     * The cheapest active plan above this one that allows more devices.
     *
     * @return array{code: string, name: string, limit: int|null, limit_label: string}|null
     */
    private function upgradeFor(MemberPlan $current, ?int $limit): ?array
    {
        if ($limit === null) {
            return null;
        }

        /** @var Collection<int, MemberPlan> $plans */
        $plans = MemberPlan::query()
            ->where('is_active', true)
            ->where('rank', '>', (int) $current->rank)
            ->orderBy('rank')
            ->with(['entitlements' => fn ($query) => $query->where('feature_key', MemberFeature::ACCOUNT_DEVICES)])
            ->get();

        foreach ($plans as $plan) {
            $row = $plan->entitlements->first();
            if ($row === null || ! $row->enabled) {
                continue;
            }
            $planLimit = $row->limit_value === null ? null : max(1, (int) $row->limit_value);
            if ($planLimit === null || $planLimit > $limit) {
                return [
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'limit' => $planLimit,
                    'limit_label' => $planLimit === null ? 'unlimited devices' : $planLimit.' '.Str::plural('device', $planLimit),
                ];
            }
        }

        return null;
    }

    private function clean(string $value, int $max): ?string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
