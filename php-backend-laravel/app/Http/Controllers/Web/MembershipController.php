<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberPlanPrice;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Services\Membership\MembershipException;
use App\Services\Membership\MembershipPresenter;
use App\Services\Membership\MemberSubscriptions;
use App\Support\Membership\MembershipSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * haraan.app/membership — where Pro and Hero are bought. The app shows plans and a member's
 * standing but doesn't sell them (store billing policy), so this page carries checkout:
 * the same MemberSubscriptions lifecycle and Razorpay subscription the API uses, over the
 * web session instead of a JWT.
 */
final class MembershipController extends Controller
{
    /** Where in Haraan each feature is felt — mirrors the app's MemberArea. */
    private const AREAS = [
        'everywhere' => 'Across Haraan',
        'events' => 'Events',
        'pulse' => 'Pulse',
        'actionboard' => 'Actionboard',
        'account' => 'Your account',
    ];

    public function __construct(
        private readonly MembershipPresenter $presenter,
        private readonly MemberSubscriptions $subscriptions,
    ) {}

    /** GET /membership */
    public function show(Request $request): View
    {
        $user = $request->user();
        if (! $user instanceof User) {
            // Signing in from the page's button comes straight back here.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        $catalogue = $this->presenter->catalogue($user instanceof User ? $user : null);
        $membership = $user instanceof User ? $this->presenter->membership($user) : null;
        $currentCode = $membership['plan']['code'] ?? $catalogue['current_plan'];
        $currentRank = (int) ($membership['plan']['rank'] ?? 0);
        $paidLive = ($membership['subscription']['provider'] ?? null) === MemberSubscription::PROVIDER_RAZORPAY;

        $terms = collect($catalogue['plans'])->flatMap(fn (array $p) => collect($p['prices'])->pluck('interval'))
            ->unique()
            ->sortBy(fn (string $i) => array_search($i, MemberPlanPrice::INTERVALS, true))
            ->values()
            ->all();

        // A signed-in member's own plan, so every other plan can say what would change for them.
        $yours = $user instanceof User
            ? collect(collect($catalogue['plans'])->firstWhere('code', $currentCode)['features'] ?? [])->keyBy('key')->map(fn (array $f) => self::value($f))->all()
            : null;

        $plans = collect($catalogue['plans'])->map(function (array $plan) use ($currentCode, $currentRank, $paidLive, $membership, $yours): array {
            $prices = collect($plan['prices'])->keyBy('interval');
            $monthly = $prices->get(MemberPlanPrice::INTERVAL_MONTH)['amount_paise'] ?? null;

            return [
                'code' => $plan['code'],
                'name' => $plan['name'],
                'tagline' => $plan['tagline'],
                'rank' => $plan['rank'],
                'is_default' => $plan['is_default'],
                'is_current' => $plan['code'] === $currentCode,
                'tier' => $plan['rank'] > 0 && in_array($plan['code'], ['pro', 'hero'], true) ? $plan['code'] : null,
                'prices' => $prices->map(fn (array $p) => [
                    'id' => $p['id'],
                    'amount' => self::rupees((int) $p['amount_paise']),
                    'per' => self::per($p['interval']),
                    'interval' => $p['interval'],
                    // Saving against paying monthly for the same span, from the real prices.
                    'saving' => $monthly === null || $p['interval'] === MemberPlanPrice::INTERVAL_MONTH ? null
                        : self::saving((int) $monthly * MemberPlanPrice::monthsIn($p['interval']), (int) $p['amount_paise']),
                    'action' => self::action($plan, $p['interval'], $currentCode, $currentRank, $paidLive, $membership),
                ])->all(),
                'sections' => self::sections($plan['features'], $plan['code'] === $currentCode ? null : $yours),
            ];
        })->all();

        return view('site.membership', [
            'title' => MembershipSettings::text('web_headline'),
            'headline' => MembershipSettings::text('web_headline'),
            'lede' => MembershipSettings::text('web_lede'),
            'seo' => ['description' => 'Haraan Pro and Hero: no ads, early ticket access, priority venue booking and advanced match insights across Events, Pulse and Actionboard.'],
            'user' => $user,
            'plans' => $plans,
            'terms' => $terms,
            'membership' => $membership,
            'status' => $membership === null ? null : self::statusLine($membership),
            'selectedPlan' => $request->query('plan'),
            'selectedTerm' => $request->query('term'),
        ]);
    }

    /** POST /membership/checkout {price_id} — creates the Razorpay subscription to pay for. */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate(['price_id' => ['required', 'integer']]);
        $price = MemberPlanPrice::query()->with('plan')->find($data['price_id']);
        if ($price === null) {
            return $this->error(new MembershipException("This plan isn't available to buy right now.", 422, 'price_unavailable'));
        }

        try {
            return response()->json(['data' => $this->subscriptions->subscribe($request->user(), $price)], 201);
        } catch (MembershipException $e) {
            return $this->error($e);
        }
    }

    /** POST /membership/verify — the signature from Razorpay's sheet; the server re-reads the outcome. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_subscription_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
        ]);

        try {
            $subscription = $this->subscriptions->verifyCheckout(
                $request->user(),
                $data['razorpay_payment_id'],
                $data['razorpay_subscription_id'],
                $data['razorpay_signature'],
            );
        } catch (MembershipException $e) {
            return $this->error($e);
        }

        return response()->json(['data' => [
            'confirmed' => $subscription->status === MemberSubscription::STATUS_ACTIVE
                || ($subscription->status === MemberSubscription::STATUS_AUTHENTICATED && $subscription->starts_at?->isFuture()),
        ]]);
    }

    /** POST /membership/abandon — the sheet was closed without paying. */
    public function abandon(Request $request): JsonResponse
    {
        $data = $request->validate(['subscription_id' => ['required', 'string', 'max:64']]);
        $this->subscriptions->abandon($request->user(), $data['subscription_id']);

        return response()->json(['data' => ['ok' => true]]);
    }

    /** POST /membership/cancel — stops renewal at the end of the paid period. */
    public function cancel(Request $request): RedirectResponse
    {
        try {
            $this->subscriptions->cancel($request->user(), true, $request->user());
        } catch (MembershipException $e) {
            return redirect()->route('site.membership')->with('membership_error', $e->getMessage());
        }

        return redirect()->route('site.membership')->with('membership_notice', "Your plan won't renew. You keep everything until the end of this period.");
    }

    private function error(MembershipException $e): JsonResponse
    {
        $status = $e->status >= 400 && $e->status < 600 ? $e->status : 422;

        return response()->json(['error' => $e->getMessage(), 'code' => $e->errorCode], $status);
    }

    // ── Presentation ─────────────────────────────────────────────────────────

    /** 9900 → "₹99", 159900 → "₹1,599" — Indian grouping. */
    public static function rupees(int $paise): string
    {
        $whole = intdiv($paise, 100);
        $digits = (string) $whole;
        if (strlen($digits) > 3) {
            $head = substr($digits, 0, -3);
            $digits = ltrim(strrev(implode(',', str_split(strrev($head), 2))), ',').','.substr($digits, -3);
        }
        $fraction = $paise % 100;

        return '₹'.$digits.($fraction === 0 ? '' : '.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT));
    }

    private static function per(string $interval): string
    {
        return match ($interval) {
            MemberPlanPrice::INTERVAL_QUARTER => '/3 months',
            MemberPlanPrice::INTERVAL_HALF_YEAR => '/6 months',
            MemberPlanPrice::INTERVAL_YEAR => '/year',
            default => '/month',
        };
    }

    private static function saving(int $full, int $price): ?int
    {
        if ($full <= 0 || $price >= $full) {
            return null;
        }
        $pct = intdiv(($full - $price) * 100, $full);

        return $pct > 0 ? $pct : null;
    }

    /**
     * What the button does for this plan at this term — mirrors the server's own classification.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>|null  $membership
     * @return array{kind: string, label: string, terms: string}
     */
    private static function action(array $plan, string $interval, string $currentCode, int $currentRank, bool $paidLive, ?array $membership): array
    {
        $sub = $membership['subscription'] ?? null;
        $renewsLabel = match ($interval) {
            MemberPlanPrice::INTERVAL_QUARTER => 'Renews every 3 months',
            MemberPlanPrice::INTERVAL_HALF_YEAR => 'Renews every 6 months',
            MemberPlanPrice::INTERVAL_YEAR => 'Renews yearly',
            default => 'Renews monthly',
        };

        if ($plan['code'] === $currentCode && (($sub['interval'] ?? null) === null || $sub['interval'] === $interval)
            && ! ($paidLive && ($sub['cancel_at_period_end'] ?? false))) {
            return ['kind' => 'current', 'label' => 'Your plan', 'terms' => ''];
        }
        if (! $paidLive) {
            return ['kind' => 'new', 'label' => 'Get '.$plan['name'], 'terms' => $renewsLabel.' · Cancel anytime'];
        }
        if ($plan['rank'] > $currentRank) {
            return ['kind' => 'upgrade', 'label' => 'Upgrade', 'terms' => 'Starts today · Current plan stops renewing'];
        }
        $on = self::date($sub['ends_at'] ?? $sub['renews_at'] ?? null);

        return ['kind' => 'switch', 'label' => 'Switch', 'terms' => ($on ? "Starts {$on}" : 'Starts when your plan ends').' · No charge until then'];
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @param  array<string, ?string>|null  $yours  the member's own plan's values by feature key
     * @return list<array{title: string, rows: list<array{name: string, value: ?string, yours: ?string, gain: bool}>}>
     */
    private static function sections(array $features, ?array $yours): array
    {
        $grouped = [];
        foreach ($features as $f) {
            $value = self::value($f);
            $have = $yours === null ? null : ($yours[$f['key']] ?? null);
            $yoursLine = match (true) {
                $yours === null, $have === $value => null,
                $have === null => 'New for you',
                $have === 'Included' => 'On your plan now',
                default => "You have {$have}",
            };
            $grouped[self::area((string) $f['key'])][] = [
                'name' => $f['name'],
                'value' => $value,
                'yours' => $yoursLine,
                'gain' => $yours !== null && $value !== null && $have === null,
            ];
        }

        $out = [];
        foreach (self::AREAS as $area => $title) {
            if (! empty($grouped[$area])) {
                $out[] = ['title' => $title, 'rows' => $grouped[$area]];
            }
        }

        return $out;
    }

    private static function area(string $key): string
    {
        return match (true) {
            $key === 'ads.hidden' => 'everywhere',
            in_array($key, ['profile.member_badge', 'support.priority'], true) => 'account',
            default => match (strstr($key, '.', true) ?: $key) {
                'events', 'tickets', 'passes' => 'events',
                'venues', 'pulse', 'bookings', 'gamehub' => 'pulse',
                'ai', 'matches', 'tournaments', 'insights', 'actionboard', 'career' => 'actionboard',
                default => 'account',
            },
        };
    }

    /** @param array<string, mixed> $f */
    private static function value(array $f): ?string
    {
        if (! $f['enabled']) {
            return null;
        }
        if ($f['type'] === 'boolean' || ($f['limit'] === null && ! $f['unlimited'])) {
            return 'Included';
        }
        if ($f['unlimited']) {
            return 'Unlimited';
        }
        if ($f['type'] === 'quota') {
            return $f['limit'].' per month';
        }
        $unit = (string) ($f['unit'] ?? '');
        if ((int) $f['limit'] === 1 && str_ends_with($unit, 's') && ! str_contains($unit, ' ')) {
            $unit = substr($unit, 0, -1);
        }

        return trim($f['limit'].' '.$unit);
    }

    /** @param array<string, mixed> $m */
    private static function statusLine(array $m): string
    {
        $sub = $m['subscription'];

        return match (true) {
            $m['attention'] === 'payment_failed' => "Your last payment didn't go through, so your plan is paused. Choose it again below to restart.",
            $m['attention'] === 'payment_retrying' => "We couldn't charge your renewal yet. Razorpay is retrying, and your plan stays on meanwhile.",
            $sub === null => "You're on the free plan.",
            $sub['provider'] === 'admin' => ($on = self::date($sub['ends_at'])) ? "Complimentary until {$on}." : 'Complimentary plan from Haraan.',
            (bool) $sub['cancel_at_period_end'] => ($on = self::date($sub['ends_at'])) ? "Ends on {$on}. You won't be charged again." : 'Ends at the end of this period.',
            $sub['renews_at'] !== null => 'Renews on '.self::date($sub['renews_at']).'.',
            default => 'Active.',
        };
    }

    private static function date(?string $iso): ?string
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        return rescue(fn () => Carbon::parse($iso)->timezone(config('app.timezone'))->format('j M Y'), null, false);
    }
}
