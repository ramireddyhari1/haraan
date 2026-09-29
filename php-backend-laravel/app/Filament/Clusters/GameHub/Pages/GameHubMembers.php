<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\Concerns\SummarisesVenues;
use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\MemberSubscription;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * The people who play at the venues: how many, how many come back, who's new,
 * and the regulars ranked by what they've actually spent. A player is a phone
 * number for desk walk-ins (they have no account) and an account otherwise.
 */
class GameHubMembers extends Page
{
    use SummarisesVenues;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $title = 'Players';

    protected static ?string $navigationLabel = 'Members & players';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.clusters.game-hub.summary-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getPanels(): array
    {
        // Walk-ins are booked under the owner's account, so the guest phone is
        // the better identity whenever there is one.
        $who = "coalesce(nullif(guest_phone, ''), 'u' || user_id)";
        $since = now()->subDays(90);
        $live = fn () => static::venueBookings()
            ->whereNotIn(DB::raw('lower(status)'), [...self::CANCELLED, 'expired'])
            ->where('created_at', '>=', $since);

        $perPlayer = $live()
            ->selectRaw("{$who} as who, COUNT(*) as n, MIN(created_at) as first_at, SUM(total_amount) as spent, MAX(user_id) as uid, MAX(guest_name) as gname")
            ->groupBy('who')
            ->get();

        $players = $perPlayer->count();
        $repeat = $perPlayer->where('n', '>=', 2)->count();
        $firstSeen = static::venueBookings()
            ->selectRaw("{$who} as who, MIN(created_at) as first_at")
            ->groupBy('who')
            ->pluck('first_at', 'who');
        $new = $perPlayer->filter(fn ($p) => ($firstSeen[$p->who] ?? null) >= now()->subDays(30)->toDateTimeString())->count();

        $top = $perPlayer->sortByDesc('spent')->take(5);
        $names = User::whereIn('id', $top->pluck('uid')->filter())->pluck('name', 'id');

        $stats = [
            ['label' => 'Players', 'value' => number_format($players), 'sub' => 'booked in the last 90 days'],
            ['label' => 'Came back', 'value' => self::pct($repeat, $players), 'sub' => number_format($repeat) . ' booked twice or more'],
            ['label' => 'New', 'value' => number_format($new), 'sub' => 'first booking in the last 30 days'],
        ];

        // Haraan memberships are an app-wide product — only meaningful in /control.
        if (! static::inPartnerConsole()) {
            $stats[] = ['label' => 'Pro & Hero members', 'value' => number_format(
                MemberSubscription::where('status', MemberSubscription::STATUS_ACTIVE)->count()
            ), 'sub' => 'active paid memberships'];
        }

        return [[
            'title' => 'Players',
            'window' => 'Last 90 days',
            'stats' => $stats,
            'list' => [
                'title' => 'Regulars by spend',
                'rows' => $top->map(fn ($p): array => [
                    'primary' => $p->gname ?: ($names[$p->uid] ?? 'Player'),
                    'secondary' => number_format((int) $p->n) . ' ' . str('booking')->plural((int) $p->n),
                    'trailing' => self::inr((float) $p->spent),
                ])->values()->all(),
                'empty' => 'No bookings in the last 90 days.',
            ],
        ]];
    }
}
