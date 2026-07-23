<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Pages\Dashboard;
use Filament\Widgets\Widget;

/**
 * "Where your views come from" — a breakdown of the partner's event-detail opens by
 * source (Instagram, WhatsApp, Search, Direct, …) over the dashboard's global period.
 * Source is tagged by EventViewRecorder from an explicit ?src/utm_source or inferred
 * from the referrer. Tells an organiser which channel to lean into.
 *
 * Event lane only + partner-scoped through event.partner_id. Self-contained Blade +
 * inline CSS, no Vite rebuild.
 */
class PartnerTrafficSourcesWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \Filament\Widgets\Concerns\InteractsWithPageFilters;

    protected string $view = 'filament.widgets.partner.traffic-sources';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Event lane only, reports capability — matches the funnel it sits beside. */
    public static function canView(): bool
    {
        $u = auth()->user();

        return $u?->partner_type === 'event'
            && ($u?->hasPartnerPermission('reports') ?? false);
    }

    /** Human label + accent for each known source key. */
    private const META = [
        'instagram' => ['Instagram', '#e1306c'],
        'whatsapp' => ['WhatsApp', '#25d366'],
        'facebook' => ['Facebook', '#1877f2'],
        'search' => ['Search', '#4285f4'],
        'google' => ['Search', '#4285f4'],
        'shared' => ['Shared links', '#8b5cf6'],
        'home' => ['Haraan browse', '#0f9d63'],
        'web' => ['Haraan web', '#0f9d63'],
        'app' => ['Haraan app', '#0f9d63'],
        'direct' => ['Direct', '#94a3b8'],
        'other' => ['Other', '#cbd5e1'],
    ];

    private function windowDays(): int
    {
        $range = (int) ($this->pageFilters['range'] ?? Dashboard::DEFAULT_PERIOD);

        return in_array($range, [7, 14, 30, 90], true) ? $range : Dashboard::DEFAULT_PERIOD;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSources(): array
    {
        $days = $this->windowDays();
        $start = now()->startOfDay()->subDays($days - 1);

        $rows = (clone $this->scopedEventViewQuery())
            ->where('created_at', '>=', $start)
            ->selectRaw('lower(source) as k, count(*) as c')
            ->groupBy('k')
            ->orderByDesc('c')
            ->get();

        $total = (int) $rows->sum('c');

        // Merge synonymous keys (search/google, web/app/home) into one display row.
        $merged = [];
        foreach ($rows as $r) {
            [$label, $color] = self::META[$r->k] ?? [ucfirst((string) $r->k), '#cbd5e1'];
            $key = $label;
            $merged[$key]['label'] = $label;
            $merged[$key]['color'] = $color;
            $merged[$key]['count'] = ($merged[$key]['count'] ?? 0) + (int) $r->c;
        }

        $sources = collect($merged)
            ->sortByDesc('count')
            ->map(fn (array $s): array => [
                'label' => $s['label'],
                'color' => $s['color'],
                'count' => $s['count'],
                'pct' => $total > 0 ? (int) round($s['count'] / $total * 100) : 0,
            ])
            ->values()
            ->all();

        return [
            'days' => $days,
            'total' => $total,
            'sources' => $sources,
            'top' => $sources[0]['label'] ?? null,
        ];
    }
}
