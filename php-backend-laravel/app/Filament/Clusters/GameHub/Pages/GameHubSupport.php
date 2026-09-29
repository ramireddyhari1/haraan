<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\Concerns\SummarisesVenues;
use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\SupportThread;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

/**
 * Support conversations (support_threads): what's open, what's waiting on a
 * reply from the Haraan team, and the latest threads. In /control that's every
 * thread; in /partner it's the ones the partner and their staff started.
 */
class GameHubSupport extends Page
{
    use SummarisesVenues;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $title = 'Support';

    protected static ?string $navigationLabel = 'Support center';

    protected static ?int $navigationSort = 14;

    protected string $view = 'filament.clusters.game-hub.summary-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    private function threads(): Builder
    {
        $q = SupportThread::query();

        if (($owner = static::partnerId()) !== null) {
            $team = User::where('parent_partner_id', $owner)->pluck('id')->push($owner);
            $q->whereIn('user_id', $team);
        }

        return $q;
    }

    public function getPanels(): array
    {
        $open = $this->threads()->where('status', '!=', 'closed');
        $openCount = (clone $open)->count();
        $waiting = (clone $open)->where('admin_unread_count', '>', 0)->count();
        $closedWeek = $this->threads()->where('status', 'closed')->where('updated_at', '>=', now()->subDays(7))->count();

        $byCategory = (clone $open)->with('category:id,label')->get()
            ->countBy(fn (SupportThread $t) => $t->category?->label ?? 'General')
            ->all();

        $latest = (clone $open)->with(['user:id,name', 'category:id,label'])
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get();

        return [[
            'title' => 'Support',
            'stats' => [
                ['label' => 'Open threads', 'value' => number_format($openCount)],
                ['label' => 'Waiting on Haraan', 'value' => number_format($waiting), 'sub' => 'unread by the support team', 'tone' => $waiting > 0 ? 'warn' : null],
                ['label' => 'Closed this week', 'value' => number_format($closedWeek)],
            ],
            'split' => ['label' => 'Open threads by topic', 'parts' => self::parts($byCategory, money: false)],
            'list' => [
                'title' => 'Most recent',
                'rows' => $latest->map(fn (SupportThread $t): array => [
                    'primary' => $t->subject ?: ($t->category?->label ?? 'Support request'),
                    'secondary' => $t->user?->name,
                    'trailing' => $t->last_message_at?->diffForHumans(short: true) ?? '',
                ])->all(),
                'empty' => 'No open support threads.',
            ],
        ]];
    }
}
