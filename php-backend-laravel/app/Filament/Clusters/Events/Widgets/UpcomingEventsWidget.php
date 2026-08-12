<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Events\Widgets;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\EventAnalytics;
use App\Models\Event;
use Filament\Widgets\Widget;

/**
 * "What's coming up" — the next few published events as premium event cards:
 * poster, when/where, a ticket sell-through meter, a health pill, and quick
 * actions (Analytics · Manage). Self-contained Blade + inline CSS, theme-aware,
 * no Vite rebuild. Each card deep-links to that event's analytics.
 */
class UpcomingEventsWidget extends Widget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;
    use \App\Filament\Concerns\ScopesToPartnerEvents;

    protected string $view = 'filament.clusters.events.widgets.upcoming-events';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * View-ready upcoming-event cards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(): array
    {
        // Skip the soonest event — the hero spotlight already leads with it.
        return $this->scopedEventQuery()
            ->whereRaw("lower(status) = 'published'")
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->offset(1)
            ->limit(6)
            ->get()
            ->map(function (Event $r): array {
                $total = max(0, (int) $r->total_slots);
                $avail = max(0, (int) $r->available_slots);
                $sold = max(0, $total - $avail);
                $pct = $total > 0 ? (int) round($sold / $total * 100) : 0;

                [$sLabel, $sTone] = match (true) {
                    $total > 0 && $avail <= 0 => ['Sold out', 'danger'],
                    $total > 0 && $pct >= 85  => ['Almost full', 'warning'],
                    $sold > 0                 => ['On sale', 'success'],
                    default                   => ['Just listed', 'gray'],
                };

                return [
                    'title'     => (string) $r->title,
                    'poster'    => $r->heroImageUrl(),
                    'whenWhere' => $this->whenWhere($r),
                    'day'       => $r->date?->format('d') ?? '',
                    'mon'       => $r->date ? strtoupper($r->date->format('M')) : '',
                    'sold'      => number_format($sold),
                    'total'     => number_format($total),
                    'pct'       => min(100, max(0, $pct)),
                    'sLabel'    => $sLabel,
                    'sTone'     => $sTone,
                    'analytics' => EventAnalytics::getUrl(['record' => $r]),
                    'manage'    => EventResource::getUrl('edit', ['record' => $r]),
                ];
            })->all();
    }

    private function whenWhere(Event $r): ?string
    {
        $bits = [];
        if ($r->date !== null) {
            // Time is its own `time` string column; `date` is date-only (else 12:00 AM).
            $time  = trim((string) $r->time);
            $bits[] = $r->date->format('D, d M') . ($time !== '' ? ' · ' . $time : '');
        }
        $place = trim((string) ($r->venue ?: $r->location));
        if ($place !== '') {
            $bits[] = $place;
        }

        return $bits === [] ? null : implode(' · ', $bits);
    }
}
