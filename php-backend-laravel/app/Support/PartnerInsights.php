<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The charts under partner Home: where the week's bookings came from, the week's bars
 * against last week's, which hours usually sit empty, and what is still open tomorrow.
 *
 * Every figure is in court-hours and rupees of LIVE bookings (not cancelled, failed,
 * expired or a checkout still on the payment screen), placed on the courts by
 * {@see CourtOccupancy} — the same rule Home's "N of M booked" and the desk grid use,
 * so a bar here can never contradict the sheet the partner works from.
 *
 * Thresholds are admin rules (`partner_insights.*` in /control → Platform rules).
 */
final class PartnerInsights
{
    /** Where a booking came from, as the partner thinks of it. */
    private const CHANNELS = ['walk_in', 'app', 'whatsapp'];

    /** @var Collection<int, Venue> */
    private Collection $venues;

    /** @var Collection<int|string, Collection<int, VenueSlot>> every available template row, by venue */
    private Collection $slotRows;

    /** @var array<int, list<int>> active court ids, by venue */
    private array $courts = [];

    /** @var array<string, true> "venueId|Y-m-d" of closed days */
    private array $blocked = [];

    /** @var Collection<string, Collection<int, Booking>> live bookings, by Y-m-d */
    private Collection $bookings;

    /** @var array<string, array<string, mixed>> day() results — the week, last week and the heatmap overlap */
    private array $days = [];

    /** @param  Collection<int, Venue>  $venues  the branches in scope */
    public function build(Collection $venues, ?string $week): array
    {
        if (! PlatformRules::bool('partner_insights.enabled')) {
            return ['enabled' => false];
        }

        $today = Carbon::parse(BusinessClock::today());
        $thisMonday = $today->copy()->startOfWeek(Carbon::MONDAY);
        $monday = $this->weekStart($week, $thisMonday);
        $prevMonday = $monday->copy()->subWeek();
        $tomorrow = $today->copy()->addDay();

        $heatWeeks = PlatformRules::int('partner_insights.heatmap_weeks');
        $heatTo = $today->copy()->subDay();
        $heatFrom = $heatTo->copy()->subDays($heatWeeks * 7 - 1);

        $from = $heatFrom->min($prevMonday)->copy();
        $to = $tomorrow->max($monday->copy()->addDays(6))->copy();

        $this->load($venues, $from, $to);

        $thisWeek = $this->week($monday, $today);
        $lastWeek = $this->week($prevMonday, $today);

        return [
            'enabled'  => true,
            'week'     => [
                'start'     => $monday->toDateString(),
                'label'     => $this->weekLabel($monday, $thisMonday),
                'prev'      => $prevMonday->toDateString(),
                'next'      => $monday->lt($thisMonday) ? $monday->copy()->addWeek()->toDateString() : null,
                'days'      => array_map(function (array $d, array $last): array {
                    $d['last_revenue'] = $last['revenue'];
                    $d['last_booked_hours'] = $last['booked_hours'];

                    return $d;
                }, $thisWeek['days'], $lastWeek['days']),
                'totals'    => $thisWeek['totals'],
                'last'      => $lastWeek['totals'],
            ],
            'channels' => [
                'today'     => $this->channels([$this->day($today)]),
                'week'      => $thisWeek['channels'],
                'last_week' => $lastWeek['channels'],
            ],
            'heatmap'  => $this->heatmap($heatFrom, $heatTo, $heatWeeks),
            'tomorrow' => $this->tomorrow($tomorrow),
        ];
    }

    /** The Monday of the week asked for; a future week reads as this one. */
    private function weekStart(?string $week, Carbon $thisMonday): Carbon
    {
        if ($week === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
            return $thisMonday->copy();
        }

        try {
            $monday = Carbon::parse($week)->startOfWeek(Carbon::MONDAY);
        } catch (\Throwable) {
            return $thisMonday->copy();
        }

        return $monday->gt($thisMonday) ? $thisMonday->copy() : $monday;
    }

    private function weekLabel(Carbon $monday, Carbon $thisMonday): string
    {
        if ($monday->eq($thisMonday)) {
            return 'This week';
        }
        if ($monday->eq($thisMonday->copy()->subWeek())) {
            return 'Last week';
        }
        $sunday = $monday->copy()->addDays(6);

        return $monday->month === $sunday->month
            ? $monday->format('j').'–'.$sunday->format('j M')
            : $monday->format('j M').' – '.$sunday->format('j M');
    }

    /** Everything the charts read, in four queries. */
    private function load(Collection $venues, Carbon $from, Carbon $to): void
    {
        $this->venues = $venues->keyBy('id');
        $ids = $venues->pluck('id');

        $this->slotRows = VenueSlot::query()->whereIn('venue_id', $ids)->where('is_available', true)
            ->get(['id', 'venue_id', 'day', 'time'])
            ->groupBy('venue_id');

        foreach (VenueCourt::query()->whereIn('venue_id', $ids)->where('is_active', true)->get(['id', 'venue_id']) as $c) {
            $this->courts[(int) $c->venue_id][] = (int) $c->id;
        }

        foreach (VenueBlockedDate::query()->whereIn('venue_id', $ids)
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->get(['venue_id', 'date']) as $row) {
            $this->blocked[(int) $row->venue_id.'|'.Carbon::parse($row->date)->toDateString()] = true;
        }

        $this->bookings = Booking::query()
            ->where('booking_type', 'venue')
            ->whereIn('venue_id', $ids)
            ->whereDate('slot_date', '>=', $from->toDateString())
            ->whereDate('slot_date', '<=', $to->toDateString())
            ->get(['id', 'venue_id', 'venue_slot_id', 'venue_court_id', 'slot_date', 'start_time', 'end_time', 'status', 'channel', 'total_amount'])
            ->filter(fn (Booking $b): bool => CourtOccupancy::isLive($b))
            ->groupBy(fn (Booking $b): string => Carbon::parse($b->slot_date)->toDateString());
    }

    /**
     * One date across every branch: capacity and booked court-hours (overall and per
     * clock hour), revenue, the channel split, and each slot's free courts.
     *
     * @return array{cap: float, booked: float, revenue: float, hours: array<int, array{0: float, 1: float}>, channels: array<string, array{amount: float, hours: float, count: int}>, venues: array<int, array{closed: bool, slots: list<array{time: string, start: int, length: int, courts: int, taken: int}>}>}
     */
    private function day(Carbon $date): array
    {
        $key = $date->toDateString();
        if (isset($this->days[$key])) {
            return $this->days[$key];
        }
        $out = [
            'cap'      => 0.0,
            'booked'   => 0.0,
            'revenue'  => 0.0,
            'hours'    => [],
            'channels' => array_fill_keys(self::CHANNELS, ['amount' => 0.0, 'hours' => 0.0, 'count' => 0]),
            'venues'   => [],
        ];
        $dayBookings = ($this->bookings->get($key) ?? collect())->groupBy('venue_id');

        foreach ($this->venues as $venueId => $venue) {
            $venueId = (int) $venueId;
            $closed = isset($this->blocked[$venueId.'|'.$key]);
            $length = $venue->slotLength();
            $slots = $closed ? collect() : VenueSlot::forDate($this->slotRows->get($venueId) ?? collect(), $date);
            $courtIds = $this->courts[$venueId] ?? [];
            $courtCount = max(count($courtIds), 1);

            // Which court of which slot is taken, once — two rows on one cell are one court-hour.
            $taken = [];
            foreach ($dayBookings->get($venueId) ?? [] as $b) {
                $cells = 0;
                foreach (CourtOccupancy::slotIdsFor($b, $slots, $length) as $slotId) {
                    foreach (CourtOccupancy::courtIdsFor($b, $courtIds) as $courtId) {
                        $taken[$slotId][$courtId] = true;
                        $cells++;
                    }
                }

                $amount = (float) $b->total_amount;
                $channel = $this->channelOf($b);
                $out['revenue'] += $amount;
                $out['channels'][$channel]['amount'] += $amount;
                $out['channels'][$channel]['hours'] += $cells * $length / 60;
                $out['channels'][$channel]['count']++;
            }

            $rows = [];
            foreach ($slots as $s) {
                $start = BookingService::timeToMinutes($s->time);
                if ($start === null) {
                    continue;
                }
                $used = min(count($taken[(int) $s->id] ?? []), $courtCount);
                $hour = intdiv($start, 60);
                $out['hours'][$hour][0] = ($out['hours'][$hour][0] ?? 0.0) + $courtCount * $length / 60;
                $out['hours'][$hour][1] = ($out['hours'][$hour][1] ?? 0.0) + $used * $length / 60;
                $out['cap'] += $courtCount * $length / 60;
                $out['booked'] += $used * $length / 60;
                $rows[] = ['time' => (string) $s->time, 'start' => $start, 'length' => $length, 'courts' => $courtCount, 'taken' => $used];
            }

            $out['venues'][$venueId] = ['closed' => $closed, 'slots' => $rows];
        }

        return $this->days[$key] = $out;
    }

    private function channelOf(Booking $b): string
    {
        return match (strtolower((string) $b->channel)) {
            'offline', 'walkin', 'walk_in', 'desk' => 'walk_in',
            'whatsapp' => 'whatsapp',
            default => 'app',
        };
    }

    /** @return array{days: list<array<string, mixed>>, totals: array<string, float>, channels: array<string, mixed>} */
    private function week(Carbon $monday, Carbon $today): array
    {
        $days = [];
        $grids = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $monday->copy()->addDays($i);
            $grid = $this->day($date);
            $grids[] = $grid;
            $days[] = [
                'date'         => $date->toDateString(),
                'label'        => $date->format('D'),
                'day'          => (int) $date->format('j'),
                'today'        => $date->eq($today),
                'future'       => $date->gt($today),
                'revenue'      => round($grid['revenue'], 2),
                'booked_hours' => round($grid['booked'], 1),
                'total_hours'  => round($grid['cap'], 1),
            ];
        }

        return [
            'days'     => $days,
            'totals'   => [
                'revenue'      => round(array_sum(array_column($grids, 'revenue')), 2),
                'booked_hours' => round(array_sum(array_column($grids, 'booked')), 1),
                'total_hours'  => round(array_sum(array_column($grids, 'cap')), 1),
            ],
            'channels' => $this->channels($grids),
        ];
    }

    /** @param  list<array<string, mixed>>  $grids */
    private function channels(array $grids): array
    {
        $sum = array_fill_keys(self::CHANNELS, ['amount' => 0.0, 'hours' => 0.0, 'count' => 0]);
        foreach ($grids as $grid) {
            foreach (self::CHANNELS as $c) {
                $sum[$c]['amount'] += $grid['channels'][$c]['amount'];
                $sum[$c]['hours'] += $grid['channels'][$c]['hours'];
                $sum[$c]['count'] += $grid['channels'][$c]['count'];
            }
        }

        return array_map(fn (array $c): array => [
            'amount' => round($c['amount'], 2),
            'hours'  => round($c['hours'], 1),
            'count'  => $c['count'],
        ], $sum);
    }

    /**
     * How full each weekday × clock hour has been over the last N weeks, and the quiet
     * stretches worth an off-peak price.
     */
    private function heatmap(Carbon $from, Carbon $to, int $weeks): array
    {
        $cap = [];
        $booked = [];
        $total = 0.0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $dow = $d->dayOfWeekIso - 1;
            foreach ($this->day($d)['hours'] as $hour => [$c, $b]) {
                $cap[$dow][$hour] = ($cap[$dow][$hour] ?? 0.0) + $c;
                $booked[$dow][$hour] = ($booked[$dow][$hour] ?? 0.0) + $b;
                $total += $b;
            }
        }

        $hours = [];
        foreach ($cap as $row) {
            $hours = array_merge($hours, array_keys($row));
        }
        if ($hours === []) {
            return ['ready' => false, 'weeks' => $weeks, 'hours' => [], 'rows' => [], 'quiet' => []];
        }
        $first = min($hours);
        $last = max($hours);

        $rows = [];
        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow => $label) {
            $cells = [];
            for ($h = $first; $h <= $last; $h++) {
                $c = $cap[$dow][$h] ?? 0.0;
                $cells[] = $c > 0 ? round(($booked[$dow][$h] ?? 0.0) / $c, 2) : null;
            }
            $rows[] = ['label' => $label, 'fill' => $cells];
        }

        // A venue with barely any bookings yet is not "quiet" at every hour — it's new.
        $ready = $total >= PlatformRules::int('partner_insights.min_bookings');

        return [
            'ready' => $ready,
            'weeks' => $weeks,
            'hours' => array_map(fn (int $h): string => $this->hourLabel($h), range($first, $last)),
            'rows'  => $rows,
            'quiet' => $ready ? $this->quietWindows($cap, $booked, $first, $last) : [],
        ];
    }

    /**
     * Runs of consecutive quiet hours, the way an owner prices them: weekdays and the
     * weekend, each averaged over its days ("Mon–Fri · 12–5 PM"). A run both share is
     * one "Every day" window. Biggest first, at most two.
     *
     * Per-day runs read as noise ("Mon, Wed, Thu 12–5 PM" beside "Fri 11 AM–5 PM") —
     * the grid above already shows each day.
     *
     * @param  array<int, array<int, float>>  $cap  court-hours on offer, [weekday][hour]
     * @param  array<int, array<int, float>>  $booked  court-hours sold, [weekday][hour]
     * @return list<array{days: string, hours: string, fill: int}>
     */
    private function quietWindows(array $cap, array $booked, int $first, int $last): array
    {
        $threshold = PlatformRules::int('partner_insights.quiet_fill_percent') / 100;
        $runsOf = function (array $days) use ($cap, $booked, $first, $last, $threshold): array {
            $runs = [];
            $start = null;
            $c = $b = 0.0;
            for ($h = $first; $h <= $last + 1; $h++) {
                $hc = $hb = 0.0;
                foreach ($days as $d) {
                    $hc += $cap[$d][$h] ?? 0.0;
                    $hb += $booked[$d][$h] ?? 0.0;
                }
                if ($h <= $last && $hc > 0 && $hb / $hc < $threshold) {
                    $start ??= $h;
                    $c += $hc;
                    $b += $hb;

                    continue;
                }
                if ($start !== null) {
                    $runs[$start.'-'.$h] = ['cap' => $c, 'booked' => $b];
                }
                $start = null;
                $c = $b = 0.0;
            }

            return $runs;
        };

        $groups = [[0, 1, 2, 3, 4], [5, 6]];
        $found = array_map($runsOf, $groups);
        $windows = [];
        foreach ($found as $g => $runs) {
            foreach ($runs as $key => $run) {
                $other = $found[1 - $g][$key] ?? null;
                if ($other !== null && $g === 1) {
                    continue; // already merged from the weekday side
                }
                $days = $other !== null ? [0, 1, 2, 3, 4, 5, 6] : $groups[$g];
                $c = $run['cap'] + ($other['cap'] ?? 0.0);
                $b = $run['booked'] + ($other['booked'] ?? 0.0);
                [$h0, $h1] = array_map('intval', explode('-', $key));
                $windows[] = [
                    'days'  => $this->dayRange($days),
                    'hours' => $this->hourRange($h0, $h1),
                    'fill'  => (int) round($c > 0 ? $b / $c * 100 : 0),
                    'score' => ($h1 - $h0) * count($days),
                ];
            }
        }
        usort($windows, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(fn (array $w): array => collect($w)->except('score')->all(), array_slice($windows, 0, 2));
    }

    /** @param  list<int>  $days  0 = Monday */
    private function dayRange(array $days): string
    {
        $names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        if (count($days) === 7) {
            return 'Every day';
        }
        $parts = [];
        $runStart = $prev = null;
        foreach ([...$days, null] as $d) {
            if ($d !== null && $prev !== null && $d === $prev + 1) {
                $prev = $d;

                continue;
            }
            if ($runStart !== null) {
                $parts[] = $prev - $runStart >= 2
                    ? $names[$runStart].'–'.$names[$prev]
                    : implode(', ', array_map(fn (int $x): string => $names[$x], range($runStart, $prev)));
            }
            $runStart = $prev = $d;
        }

        return implode(', ', $parts);
    }

    private function hourLabel(int $h): string
    {
        $h %= 24;

        return ($h % 12 === 0 ? 12 : $h % 12).($h < 12 ? ' AM' : ' PM');
    }

    /** "2–5 PM", or "11 AM–2 PM" across noon. */
    private function hourRange(int $from, int $to): string
    {
        $a = $this->hourLabel($from);
        $b = $this->hourLabel($to);

        return substr($a, -2) === substr($b, -2) ? substr($a, 0, -3).'–'.$b : $a.'–'.$b;
    }

    /** Tomorrow's unsold court-hours per branch, in runs the partner can share. */
    private function tomorrow(Carbon $date): array
    {
        $grid = $this->day($date);
        $venues = [];
        foreach ($grid['venues'] as $venueId => $v) {
            $venue = $this->venues->get($venueId);
            $open = 0.0;
            $total = 0.0;
            $windows = [];
            $run = null;
            foreach ($v['slots'] as $s) {
                $free = $s['courts'] - $s['taken'];
                $open += $free * $s['length'] / 60;
                $total += $s['courts'] * $s['length'] / 60;
                if ($free > 0 && $run !== null && $run['end'] === $s['start']) {
                    $run['end'] = $s['start'] + $s['length'];
                    $run['free'] = min($run['free'], $free);

                    continue;
                }
                if ($run !== null) {
                    $windows[] = $run;
                    $run = null;
                }
                if ($free > 0) {
                    $run = ['start' => $s['start'], 'end' => $s['start'] + $s['length'], 'free' => $free, 'from' => $s['time']];
                }
            }
            if ($run !== null) {
                $windows[] = $run;
            }
            usort($windows, fn (array $a, array $b): int => ($b['end'] - $b['start']) <=> ($a['end'] - $a['start']));

            $top = array_slice($windows, 0, 3);
            usort($top, fn (array $a, array $b): int => $a['start'] <=> $b['start']);
            $link = url('/gamehub/'.$venueId);

            $venues[] = [
                'id'          => $venueId,
                'name'        => (string) $venue->name,
                'closed'      => $v['closed'],
                'open_hours'  => round($open, 1),
                'total_hours' => round($total, 1),
                'windows'     => array_map(fn (array $w): array => [
                    'label'       => $this->clock($w['start']).' – '.$this->clock($w['end']),
                    'free_courts' => $w['free'],
                ], $top),
                'share_url'   => $link,
                'share_text'  => strtr(PlatformRules::string('partner_insights.share_message'), [
                    '{venue}' => (string) $venue->name,
                    '{times}' => implode(', ', array_map(fn (array $w): string => $this->clock($w['start']).'–'.$this->clock($w['end']), $top)),
                    '{link}'  => $link,
                ]),
            ];
        }

        return [
            'date'  => $date->toDateString(),
            'label' => $date->format('D, j M'),
            'venues' => $venues,
        ];
    }

    private function clock(int $minutes): string
    {
        return Carbon::today()->addMinutes($minutes)->format($minutes % 60 === 0 ? 'g A' : 'g:i A');
    }
}
