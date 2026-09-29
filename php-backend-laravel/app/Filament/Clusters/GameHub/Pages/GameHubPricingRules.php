<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\Concerns\SummarisesVenues;
use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\PricingRule;
use App\Models\VenueCourt;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * The price adjustments actually configured: pricing_rules rows (time-of-day,
 * weekday, seasonal, last-minute…) and the per-court peak prices. Nothing here
 * is computed or predicted — it lists what will change a price, and by how much.
 */
class GameHubPricingRules extends Page
{
    use SummarisesVenues;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-rupee';

    protected static ?string $title = 'Pricing rules';

    protected static ?string $navigationLabel = 'Pricing rules';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.clusters.game-hub.summary-page';

    private const TYPE_NAMES = [
        'time_of_day' => 'Time of day',
        'day_of_week' => 'Day of week',
        'seasonal_date_range' => 'Dates',
        'occupancy_surge' => 'Busy-slot surge',
        'last_minute' => 'Last minute',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getPanels(): array
    {
        $rules = fn () => static::ownVenues(PricingRule::query());
        $active = $rules()->where('is_active', true)->with(['venue:id,name', 'venueCourt:id,name'])->orderByDesc('priority')->get();
        $paused = $rules()->where('is_active', false)->count();

        $courts = fn () => static::ownVenues(VenueCourt::query()->where('is_active', true));
        $courtCount = $courts()->count();
        $peakCourts = $courts()->where('peak_price', '>', 0)->count();
        $avgRate = (float) $courts()->where('price', '>', 0)->avg('price');

        return [[
            'title' => 'Pricing',
            'stats' => [
                ['label' => 'Active rules', 'value' => number_format($active->count()), 'sub' => $paused > 0 ? number_format($paused) . ' paused' : null],
                ['label' => 'Courts with a peak price', 'value' => number_format($peakCourts), 'sub' => 'of ' . number_format($courtCount) . ' active courts'],
                ['label' => 'Average base rate', 'value' => $avgRate > 0 ? self::inr($avgRate) . '/hr' : '—', 'sub' => 'across active courts'],
            ],
            'split' => [
                'label' => 'Active rules by kind',
                'parts' => self::parts($active->countBy(fn (PricingRule $r) => self::TYPE_NAMES[$r->rule_type] ?? ucfirst((string) $r->rule_type))->all(), money: false),
            ],
            'list' => [
                'title' => 'Rules in force',
                'rows' => $active->take(8)->map(fn (PricingRule $r): array => [
                    'primary' => $r->name ?: (self::TYPE_NAMES[$r->rule_type] ?? 'Rule'),
                    'secondary' => collect([$r->venue?->name, $r->venueCourt?->name ?? 'all courts', $this->when($r)])->filter()->join(' · '),
                    'trailing' => $this->change($r),
                ])->values()->all(),
                'empty' => 'No pricing rules are switched on — every court sells at its base (or peak) price.',
            ],
        ]];
    }

    private function change(PricingRule $r): string
    {
        $amount = (float) $r->amount;

        return match ($r->pricing_mode) {
            'absolute' => self::inr($amount) . ' flat',
            'percentage' => ($amount >= 0 ? '+' : '−') . rtrim(rtrim(number_format(abs($amount), 1), '0'), '.') . '%',
            default => ($amount >= 0 ? '+' : '−') . self::inr(abs($amount)),
        };
    }

    private function when(PricingRule $r): ?string
    {
        $parts = [];

        if (is_array($r->weekdays) && $r->weekdays !== []) {
            $parts[] = collect($r->weekdays)
                // Stored as day names ("monday"); tolerate a numeric day too.
                ->map(fn ($d) => is_numeric($d)
                    ? Carbon::create()->startOfWeek(Carbon::SUNDAY)->addDays((int) $d)->format('D')
                    : ucfirst(substr(strtolower((string) $d), 0, 3)))
                ->join(', ');
        }
        if ($r->start_time && $r->end_time) {
            $parts[] = substr((string) $r->start_time, 0, 5) . '–' . substr((string) $r->end_time, 0, 5);
        }
        if ($r->date_from || $r->date_to) {
            $parts[] = trim(($r->date_from?->format('j M') ?? '') . ' – ' . ($r->date_to?->format('j M') ?? ''), ' –');
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
