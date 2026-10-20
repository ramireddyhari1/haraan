<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionResource;
use App\Filament\Resources\PartnerPayoutAccounts\PartnerPayoutAccountResource;
use App\Filament\Resources\PayoutBatches\PayoutBatchResource;
use App\Models\Booking;
use App\Models\Event;
use App\Models\MemberSubscription;
use App\Models\PartnerPayoutAccount;
use App\Models\Payout;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Models\Venue;
use App\Services\PartnerSettlement;
use App\Support\BusinessClock;
use App\Support\FormatsSummaries;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where the money is: what came in this month and what Haraan kept, what partners are
 * owed and whether they can be paid, and the memberships.
 *
 * Replaces four tiles that misread the books: "Revenue" was gross booking value (the
 * partners' money plus ours), and pending/settled payouts read only the legacy
 * per-booking payouts table, so every payout batch — the way partners are actually
 * paid — was invisible. Balances here come from PartnerSettlement, the same maths as
 * the payout batch form and the partner's own console.
 */
class FinanceOverview extends Page
{
    use FormatsSummaries;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'Finance Overview';

    protected static ?string $navigationLabel = 'Overview';

    /** Finance only. The cluster also admits partner managers, for Settlement accounts. */
    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.clusters.finance.finance-overview';

    /** @return list<array<string, mixed>> */
    public function getPanels(): array
    {
        return [$this->month(), $this->partners(), $this->members()];
    }

    private function monthStart(): Carbon
    {
        return BusinessClock::now()->startOfMonth()->setTimezone(config('app.timezone'));
    }

    private function month(): array
    {
        $paid = fn () => Booking::query()
            ->whereIn(DB::raw('lower(status)'), PartnerSettlement::PAID_BOOKING)
            ->where('created_at', '>=', $this->monthStart());

        $gross = (float) $paid()->sum('total_amount');
        // What Haraan itself keeps — the same expression as the Command Center: customer-paid
        // platform and gateway fees, event convenience fees, and what came off hosts' payouts.
        $ours = (float) $paid()->sum(DB::raw(
            "platform_fee + gateway_fee + host_deduction + CASE WHEN booking_type = 'venue' THEN 0 ELSE convenience_fee END"
        ));
        $tax = (float) $paid()->sum('tax_amount');
        $refunded = (float) Booking::query()->whereRaw('lower(status) = ?', ['refunded'])
            ->where('updated_at', '>=', $this->monthStart())->sum('total_amount');

        $events = (float) $paid()->whereNotNull('event_id')->sum('total_amount');
        $venues = (float) $paid()->whereNotNull('venue_id')->sum('total_amount');

        return [
            'title' => 'This month',
            'window' => BusinessClock::now()->startOfMonth()->format('j M') . ' – ' . BusinessClock::now()->format('j M'),
            'stats' => [
                ['label' => 'Paid bookings', 'value' => self::inr($gross), 'sub' => 'gross, before anyone\'s share'],
                ['label' => 'Haraan kept', 'value' => self::inr($ours), 'sub' => self::pct($ours, $gross) . ' of gross — fees and commission', 'tone' => $ours > 0 ? 'good' : null],
                ['label' => 'Tax held', 'value' => self::inr($tax), 'sub' => 'owed to the tax authority'],
                ['label' => 'Refunded', 'value' => self::inr($refunded), 'tone' => $refunded > 0 ? 'warn' : null],
            ],
            'split' => ['label' => 'Paid bookings by line of business', 'parts' => self::parts(['Events' => $events, 'Venues' => $venues])],
        ];
    }

    private function partners(): array
    {
        $settlement = app(PartnerSettlement::class);

        // Only partners who have ever been paid for something can be owed anything.
        $ids = Booking::query()
            ->whereIn(DB::raw('lower(status)'), PartnerSettlement::PAID_BOOKING)
            ->where(fn ($q) => $q->whereNotNull('event_id')->orWhereNotNull('venue_id'))
            ->get(['event_id', 'venue_id'])
            ->pipe(fn ($rows) => Event::whereIn('id', $rows->pluck('event_id')->filter())->pluck('partner_id')
                ->merge(Venue::whereIn('id', $rows->pluck('venue_id')->filter())->pluck('partner_id')))
            ->filter()->unique()->values();

        $accounts = PartnerPayoutAccount::query()->whereIn('partner_id', $ids)->get()->keyBy('partner_id');
        $names = User::whereIn('id', $ids)->pluck('name', 'id');

        $owed = $ids->map(fn ($id): array => ['id' => (int) $id, 'available' => $settlement->available((int) $id)])
            ->filter(fn (array $r): bool => $r['available'] >= 1)
            ->sortByDesc('available')
            ->values();

        $blocked = $owed->filter(fn (array $r): bool => ! $accounts->has($r['id']) || $accounts[$r['id']]->verified_at === null);

        $inFlight = (float) PayoutBatch::query()->whereIn(DB::raw('lower(status)'), ['processing', 'pending'])->sum('amount');
        $paidMonth = (float) PayoutBatch::query()
            ->whereIn(DB::raw('lower(status)'), PayoutBatch::PAID)
            ->where(DB::raw('COALESCE(processed_at, updated_at)'), '>=', $this->monthStart())
            ->sum('amount')
            + (float) Payout::query()
                ->whereIn(DB::raw('lower(status)'), PartnerSettlement::SETTLED_PAYOUT)
                ->where('updated_at', '>=', $this->monthStart())
                ->sum('amount');

        return [
            'title' => 'Partners',
            'stats' => [
                ['label' => 'Owed now', 'value' => self::inr($owed->sum('available')), 'sub' => $owed->count() . ' ' . str('partner')->plural($owed->count()) . ' with a balance', 'tone' => $owed->isNotEmpty() ? 'warn' : null],
                ['label' => 'Can\'t be paid yet', 'value' => number_format($blocked->count()), 'sub' => 'no verified payout account', 'tone' => $blocked->isNotEmpty() ? 'warn' : null],
                ['label' => 'In a payout batch', 'value' => self::inr($inFlight), 'sub' => 'net, on its way'],
                ['label' => 'Paid out this month', 'value' => self::inr($paidMonth), 'sub' => 'net to partners\' banks'],
            ],
            'list' => [
                'title' => 'Owed the most',
                'rows' => $owed->take(6)->map(function (array $r) use ($accounts, $names): array {
                    $account = $accounts[$r['id']] ?? null;
                    $ready = $account !== null && $account->verified_at !== null;

                    return [
                        'primary' => $names[$r['id']] ?? 'Partner #' . $r['id'],
                        'secondary' => match (true) {
                            $account === null => 'No payout account — ask them to add one',
                            ! $ready => 'Payout account not verified yet',
                            default => 'Ready to pay · ' . strtoupper((string) $account->method),
                        },
                        'trailing' => self::inr($r['available']),
                        // Ready: open a batch for them. Otherwise: their payout account.
                        'url' => $ready
                            ? PayoutBatchResource::getUrl('create', ['partner' => $r['id']])
                            : PartnerPayoutAccountResource::getUrl('index'),
                    ];
                })->all(),
                'empty' => 'Nobody is owed anything — every partner balance is settled.',
                'illo' => 'receipt',
            ],
        ];
    }

    private function members(): array
    {
        if (! Schema::hasTable('member_subscriptions')) {
            return ['title' => 'Memberships', 'stats' => []];
        }

        $active = MemberSubscription::query()->where('status', MemberSubscription::STATUS_ACTIVE);
        $paying = (clone $active)->whereNull('granted_by')->count();
        $granted = (clone $active)->whereNotNull('granted_by')->count();

        $collected = Schema::hasTable('member_payments')
            ? (int) DB::table('member_payments')->where('status', 'captured')
                ->where(DB::raw('COALESCE(paid_at, created_at)'), '>=', $this->monthStart())
                ->sum('amount_paise') / 100
            : 0;

        $ending = (clone $active)->where('cancel_at_period_end', true)
            ->whereBetween('current_period_end', [now(), now()->addDays(7)])->count();

        // Razorpay couldn't charge the card: the member keeps nothing unless someone acts.
        $trouble = MemberSubscription::query()
            ->whereIn('status', [MemberSubscription::STATUS_HALTED, MemberSubscription::STATUS_PENDING])
            ->with(['user:id,name', 'plan:id,name'])
            ->latest('last_event_at')
            ->limit(6)
            ->get();

        return [
            'title' => 'Memberships',
            'stats' => [
                ['label' => 'Paying members', 'value' => number_format($paying), 'sub' => $granted > 0 ? '+ ' . number_format($granted) . ' granted free' : null],
                ['label' => 'Collected this month', 'value' => self::inr((float) $collected), 'tone' => $collected > 0 ? 'good' : null],
                ['label' => 'Ending this week', 'value' => number_format($ending), 'sub' => 'cancelled, period runs out'],
                ['label' => 'Payment trouble', 'value' => number_format($trouble->count()), 'sub' => 'renewal failed or pending', 'tone' => $trouble->isNotEmpty() ? 'warn' : null],
            ],
            'list' => [
                'title' => 'Renewals that failed',
                'rows' => $trouble->map(fn (MemberSubscription $s): array => [
                    'primary' => $s->user?->name ?? 'Member #' . $s->user_id,
                    'secondary' => ($s->plan?->name ?? 'Plan') . ' · ' . ucfirst((string) $s->status),
                    'trailing' => $s->last_event_at?->diffForHumans(short: true) ?? '',
                    'url' => MemberSubscriptionResource::getUrl('view', ['record' => $s]),
                ])->all(),
                'empty' => 'Every renewal went through.',
                'illo' => 'players',
            ],
        ];
    }
}
