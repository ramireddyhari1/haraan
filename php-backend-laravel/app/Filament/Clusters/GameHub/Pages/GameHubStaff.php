<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\Concerns\SummarisesVenues;
use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\ShiftSession;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Desk staff: the accounts that exist, who has a drawer open right now, and
 * today's shifts. "On duty" means an open shift session — the only record of
 * someone actually working — not a roster of names.
 */
class GameHubStaff extends Page
{
    use SummarisesVenues;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $title = 'Staff';

    protected static ?string $navigationLabel = 'Staff operations';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.clusters.game-hub.summary-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getPanels(): array
    {
        $staff = User::query()->whereNotNull('parent_partner_id');
        if (($owner = static::partnerId()) !== null) {
            $staff->where('parent_partner_id', $owner);
        }
        $staffCount = (clone $staff)->count();

        $shifts = fn () => static::ownVenues(ShiftSession::query());
        $open = $shifts()->open()->with(['staff:id,name', 'venue:id,name'])->orderBy('opened_at')->get();
        $today = $shifts()->where(fn ($q) => $q->whereDate('opened_at', Carbon::today())->orWhereDate('closed_at', Carbon::today()))->count();
        $closedToday = $shifts()->whereDate('closed_at', Carbon::today());
        $off = (clone $closedToday)->where(fn ($q) => $q->where('variance', '>=', 1)->orWhere('variance', '<=', -1))->count();

        return [[
            'title' => 'Staff',
            'stats' => [
                ['label' => 'Staff accounts', 'value' => number_format($staffCount)],
                ['label' => 'On duty now', 'value' => number_format($open->count()), 'sub' => 'with a drawer open'],
                ['label' => 'Shifts today', 'value' => number_format($today)],
                ['label' => 'Closed off-balance', 'value' => number_format($off), 'sub' => 'today, counted ≠ expected', 'tone' => $off > 0 ? 'warn' : null],
            ],
            'list' => [
                'title' => 'On duty now',
                'rows' => $open->map(fn (ShiftSession $s): array => [
                    'primary' => $s->staff?->name ?? 'Unassigned',
                    'secondary' => $s->venue?->name,
                    'trailing' => $s->opened_at ? 'since ' . $s->opened_at->format('g:i A') : '',
                ])->all(),
                'empty' => 'Nobody has a shift open right now.',
            ],
        ]];
    }
}
