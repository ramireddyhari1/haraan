<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBlocks\Widgets;

use App\Filament\Widgets\ListSummaryWidget;
use App\Models\VenueBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Blocked-time list summary: blocks in force today, what is coming up, why
 * courts are being held, and the next few holds in date order.
 */
class VenueBlocksExecutiveHeroWidget extends ListSummaryWidget
{
    public function getSummary(): array
    {
        $today = Carbon::today();
        $week = $today->copy()->addDays(7);

        // Same rule as VenueBlock::scopeApplyingOn, across every venue.
        $inForceToday = VenueBlock::query()
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('weekday')->orWhere('weekday', $today->dayOfWeek))
            ->count();

        $upcoming = VenueBlock::whereDate('starts_on', '>', $today->toDateString())
            ->whereDate('starts_on', '<=', $week->toDateString())
            ->count();

        $current = VenueBlock::whereDate('ends_on', '>=', $today->toDateString());
        $recurring = (clone $current)->whereNotNull('weekday')->count();

        $byKind = (clone $current)
            ->selectRaw('kind, COUNT(*) as n')
            ->groupBy('kind')
            ->pluck('n', 'kind')
            ->mapWithKeys(fn ($n, $k) => [VenueBlock::KINDS[$k] ?? ucfirst((string) ($k ?: 'Other')) => (int) $n])
            ->all();

        $next = VenueBlock::with(['venue:id,name', 'court:id,name'])
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->orderBy('starts_on')
            ->limit(4)
            ->get();

        return [
            'title' => 'Blocked time',
            'stats' => [
                ['label' => 'In force today', 'value' => number_format($inForceToday)],
                ['label' => 'Starting this week', 'value' => number_format($upcoming)],
                ['label' => 'Weekly repeats', 'value' => number_format($recurring), 'sub' => 'still running'],
                ['label' => 'All blocks', 'value' => number_format(VenueBlock::count())],
            ],
            'split' => [
                'label' => 'Why courts are held (current and upcoming)',
                'parts' => self::parts($byKind, money: false),
            ],
            'list' => [
                'title' => 'Next up',
                'rows' => $next->map(fn (VenueBlock $b): array => [
                    'primary' => $b->title ?: (VenueBlock::KINDS[$b->kind] ?? 'Block'),
                    'secondary' => collect([$b->venue?->name, $b->court?->name ?? 'whole venue'])->filter()->join(' · '),
                    'trailing' => $this->when($b),
                ])->all(),
                'empty' => 'No courts are blocked now or coming up.',
            ],
        ];
    }

    private function when(VenueBlock $b): string
    {
        $dates = $b->starts_on?->equalTo($b->ends_on)
            ? $b->starts_on->format('j M')
            : $b->starts_on?->format('j M') . ' – ' . $b->ends_on?->format('j M');

        if ($b->weekday !== null) {
            $dates = 'Every ' . Carbon::create()->startOfWeek(Carbon::SUNDAY)->addDays($b->weekday)->format('l') . ' · ' . $dates;
        }

        if ($b->start_time && $b->end_time) {
            $dates .= ' · ' . substr((string) $b->start_time, 0, 5) . '–' . substr((string) $b->end_time, 0, 5);
        }

        return $dates;
    }
}
