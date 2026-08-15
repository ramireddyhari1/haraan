<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Venue;

use App\Filament\Widgets\Venue\Concerns\ScopesToVenueLane;
use App\Models\Booking;
use App\Models\Venue;
use App\Support\PartnerBranchContext;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which branch is soft this week.
 *
 * The owner intelligence layer: one row per outlet, ranked, so a chain owner can
 * answer the only question a multi-branch dashboard is actually for. A merged
 * total cannot answer it — "the business made ₹4.8L" is true and useless when
 * one of three outlets is carrying the other two.
 *
 * **Utilisation is the honest column, and the sort default.** Revenue and
 * bookings both reward the biggest branch: the outlet with twelve courts will
 * out-earn the one with four however badly it is run. Occupancy against its own
 * capacity is the only measure that compares a branch to itself, which is what
 * "underperforming" has to mean.
 *
 * Shown only when there is more than one branch AND the switcher is on "All
 * branches" — comparing one outlet to itself is a table with one row, and the
 * page already says which branch you picked.
 */
class BranchComparisonWidget extends Widget
{
    use ScopesToVenueLane;
    use \Filament\Widgets\Concerns\InteractsWithPageFilters;

    protected string $view = 'filament.widgets.venue.branch-comparison';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -2;

    /** Custom v4 widgets render blank unless this is false. */
    protected static bool $isLazy = false;

    /** A branch is flagged soft below this share of the chain's best utilisation. */
    private const SOFT_RATIO = 0.7;

    /**
     * The best branch must itself clear this utilisation (%) before ANY branch is
     * called soft.
     *
     * Without it the comparison is nonsense at low volume: on a chain whose best
     * outlet is running at 1%, a ratio test happily reports "Powai is running well
     * below your best outlet" — which reads as *Powai* has a problem, when in fact
     * the whole business is quiet and Bandra is no better. A relative measure needs
     * something real to be relative to.
     *
     * Found by running this against production data, not in a test: the fixtures
     * all had healthy utilisation because that is what you write when you are
     * writing the happy path.
     */
    private const MIN_BEST_UTILISATION = 5;

    public static function canView(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return false;
        }

        // Inherit the branch-lane gate (sports + café, never event hosts).
        $laneOk = (function (): bool {
            $lane = auth()->user()?->partnerLane();

            return $lane !== null && \App\Support\PartnerLane::isBranchLane($lane);
        })();

        return $laneOk
            && PartnerBranchContext::isMultiBranch()
            && PartnerBranchContext::currentId() === null;
    }

    /** The window (days) from the dashboard's one global period control. */
    private function windowDays(): int
    {
        $range = (int) ($this->pageFilters['range'] ?? \App\Filament\Pages\Dashboard::DEFAULT_PERIOD);

        return array_key_exists((string) $range, \App\Filament\Pages\Dashboard::PERIODS)
            ? $range
            : \App\Filament\Pages\Dashboard::DEFAULT_PERIOD;
    }

    /**
     * One row per branch, best utilisation first.
     *
     * Deliberately a handful of aggregate queries rather than per-branch loops:
     * a chain has tens of outlets, not thousands, but N+1 here would be three
     * queries per row on the dashboard's hot path.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        $days = $this->windowDays();
        $since = Carbon::today()->subDays($days - 1);

        /** @var Collection<int, Venue> $branches */
        $branches = Venue::query()
            ->whereIn('id', $this->venueIds())
            ->withCount(['courts' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('branch_label')
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty()) {
            return [];
        }

        $ids = $branches->pluck('id');

        // Money COLLECTED in the window, per branch — from the payment ledger, so
        // a ₹4,400 booking with a ₹500 advance counts ₹500. Using total_amount
        // would overstate every branch by whatever it hasn't been paid yet.
        // NB: aliased, and plucked BY the alias. `pluck(DB::raw('SUM(x.y)'), …)`
        // splits the expression on its dot and looks for a property called "y)".
        $revenue = DB::table('booking_payments')
            ->join('bookings', 'bookings.id', '=', 'booking_payments.booking_id')
            ->whereIn('bookings.venue_id', $ids)
            ->whereDate('booking_payments.collected_at', '>=', $since)
            ->groupBy('bookings.venue_id')
            ->selectRaw('bookings.venue_id as vid, SUM(booking_payments.amount) as total')
            ->pluck('total', 'vid');

        $sold = (clone $this->liveBookings())
            ->whereIn('venue_id', $ids)
            ->whereDate('slot_date', '>=', $since)
            ->groupBy('venue_id')
            ->selectRaw('venue_id as vid, COUNT(*) as total')
            ->pluck('total', 'vid');

        // Hours sold per branch, for the utilisation numerator.
        $hours = (clone $this->liveBookings())
            ->whereIn('venue_id', $ids)
            ->whereDate('slot_date', '>=', $since)
            ->get(['venue_id', 'start_time', 'end_time'])
            ->groupBy('venue_id')
            ->map(fn (Collection $rows): int => (int) $rows->sum(
                fn (Booking $b): int => $this->hoursBetween($b->start_time, $b->end_time),
            ));

        $rows = $branches->map(function (Venue $v) use ($revenue, $sold, $hours, $days): array {
            $courts = max(1, (int) $v->courts_count);
            // Rough on purpose: courts × a 14-hour day × the window. A precise
            // denominator needs per-day opening hours, and a roughly-right
            // utilisation today beats a perfect one next quarter — it only has
            // to rank branches against each other consistently.
            $sellable = $courts * 14 * $days;
            $used = (int) ($hours[$v->id] ?? 0);

            return [
                'id' => $v->id,
                'branch' => $v->branchName(),
                'code' => $v->branch_code,
                'revenue' => (float) ($revenue[$v->id] ?? 0),
                'bookings' => (int) ($sold[$v->id] ?? 0),
                'utilisation' => $sellable > 0 ? (int) round(min(100, $used / $sellable * 100)) : 0,
                'resources' => $courts,
                'is_active' => (bool) $v->is_active,
            ];
        })->all();

        usort($rows, fn (array $a, array $b): int => $b['utilisation'] <=> $a['utilisation']);

        // Flag the soft ones RELATIVE to the chain's own best, not an absolute
        // number: what counts as a bad Tuesday differs by city and by sport, but
        // "well below what your best outlet manages" is comparable anywhere —
        // provided the best outlet is actually managing something.
        $best = max(array_column($rows, 'utilisation'));
        $comparable = $best >= self::MIN_BEST_UTILISATION;

        foreach ($rows as $i => $row) {
            $rows[$i]['is_soft'] = $comparable
                && $row['utilisation'] < $best * self::SOFT_RATIO
                && $row['is_active'];
            $rows[$i]['share'] = $best > 0 ? (int) round($row['utilisation'] / $best * 100) : 0;
        }

        return $rows;
    }

    /** The one-line read above the table. Null when nothing needs saying. */
    public function getHeadline(): ?string
    {
        $rows = $this->getRows();
        $soft = array_values(array_filter($rows, fn (array $r): bool => $r['is_soft']));

        if ($soft === []) {
            return null;
        }

        $names = implode(' and ', array_map(fn (array $r): string => $r['branch'], array_slice($soft, 0, 2)));
        $extra = count($soft) > 2 ? sprintf(' and %d more', count($soft) - 2) : '';

        return sprintf(
            '%s%s %s running well below your best outlet.',
            $names,
            $extra,
            count($soft) === 1 && $extra === '' ? 'is' : 'are',
        );
    }

    public function getWindowLabel(): string
    {
        return \App\Filament\Pages\Dashboard::PERIODS[(string) $this->windowDays()]
            ?? 'Last '.$this->windowDays().' days';
    }

    /** "court" / "table", so the capacity column speaks the lane's language. */
    public function getResourceLabel(): string
    {
        return ucfirst($this->resourceNoun(plural: true));
    }

    public function formatMoney(float $n): string
    {
        return $this->inr($n);
    }

    /** Duration of a booking in whole hours, defaulting to 1 when unknown. */
    private function hoursBetween(?string $start, ?string $end): int
    {
        if ($start === null || $end === null) {
            return 1;
        }

        $s = strtotime($start);
        $e = strtotime($end);

        if ($s === false || $e === false || $e <= $s) {
            return 1;
        }

        return max(1, (int) round(($e - $s) / 3600));
    }
}
