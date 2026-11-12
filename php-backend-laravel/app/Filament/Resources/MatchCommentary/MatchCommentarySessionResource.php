<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchCommentary;

use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Filament\Resources\MatchCommentary\Pages\ListMatchCommentarySessions;
use App\Models\AdminAction;
use App\Models\MatchCommentaryReport;
use App\Models\MatchCommentarySession;
use App\Services\MatchCommentary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * /control → GameHub → Live commentary. Who is on air right now, who was, how many listened,
 * how it ended, and what listeners reported. The one action is Take off air: it closes the
 * LiveKit room at once (every listener drops) and the commentator's phone is told on its next
 * heartbeat. The rules (switch, limits, report threshold) are on the Commentary rules page.
 */
class MatchCommentarySessionResource extends Resource
{
    protected static ?string $model = MatchCommentarySession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-microphone';

    protected static ?string $cluster = GameHubCluster::class;

    protected static ?string $navigationLabel = 'Live commentary';

    protected static ?string $modelLabel = 'broadcast';

    protected static ?string $pluralModelLabel = 'Live commentary';

    protected static ?string $slug = 'live-commentary';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $live = MatchCommentarySession::query()->open()
            ->where('last_heartbeat_at', '>=', now()->subSeconds(MatchCommentary::STALE_AFTER_SECONDS))
            ->count();

        return $live > 0 ? $live.' on air' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['match', 'user', 'endedBy'])
            ->withCount('reports');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->poll('15s')
            ->columns([
                TextColumn::make('state')
                    ->label('')
                    ->badge()
                    ->getStateUsing(fn (MatchCommentarySession $s): string => self::isLive($s) ? ($s->muted ? 'Muted' : 'On air') : 'Ended')
                    ->color(fn (string $state): string => match ($state) {
                        'On air' => 'danger',
                        'Muted' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label('Commentator')
                    ->weight('bold')
                    ->description(fn (MatchCommentarySession $s): string => (string) ($s->user?->player_id ?: $s->user?->email ?: ''))
                    ->searchable(),
                TextColumn::make('match.title')
                    ->label('Match')
                    ->description(fn (MatchCommentarySession $s): string => '#'.$s->match_id.' · '.ucfirst((string) $s->match?->sport).($s->match?->is_private ? ' · private' : ''))
                    ->wrap(),
                TextColumn::make('listeners')
                    ->label('Listeners')
                    ->getStateUsing(fn (MatchCommentarySession $s): string => self::isLive($s)
                        ? $s->listeners_now.' now · '.$s->peak_listeners.' peak'
                        : $s->peak_listeners.' peak'),
                TextColumn::make('reports_count')
                    ->label('Reports')
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('started_at')->label('Started')->since()->sortable(),
                TextColumn::make('duration')
                    ->label('On air for')
                    ->getStateUsing(fn (MatchCommentarySession $s): string => self::duration($s->durationSeconds())),
                TextColumn::make('ended_reason')
                    ->label('How it ended')
                    ->formatStateUsing(fn (?string $state): string => MatchCommentarySession::ENDED_REASONS[$state ?? ''] ?? '—')
                    ->description(fn (MatchCommentarySession $s): ?string => $s->endedBy?->name ? 'by '.$s->endedBy->name : null)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['live' => 'On air now', 'ended' => 'Ended', 'reported' => 'Reported'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'live' => $query->whereNull('ended_at')->where('last_heartbeat_at', '>=', now()->subSeconds(MatchCommentary::STALE_AFTER_SECONDS)),
                        'ended' => $query->whereNotNull('ended_at'),
                        'reported' => $query->has('reports'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('reports')
                    ->label('Reports')
                    ->icon('heroicon-o-flag')
                    ->color('gray')
                    ->visible(fn (MatchCommentarySession $s): bool => (int) $s->reports_count > 0)
                    ->modalHeading('What listeners reported')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (MatchCommentarySession $s) => new \Illuminate\Support\HtmlString(
                        '<ul style="display:grid;gap:10px">'.$s->reports()->with('reporter')->latest()->get()->map(
                            fn (MatchCommentaryReport $r): string => '<li><strong>'.e(MatchCommentaryReport::REASONS[$r->reason] ?? $r->reason).'</strong> · '
                                .e($r->reporter?->name ?? 'Guest listener').' · '.e($r->created_at?->diffForHumans() ?? '')
                                .($r->details ? '<br><span style="opacity:.75">“'.e($r->details).'”</span>' : '').'</li>'
                        )->implode('').'</ul>'
                    )),
                Action::make('take_off_air')
                    ->label('Take off air')
                    ->icon('heroicon-o-speaker-x-mark')
                    ->color('danger')
                    ->visible(fn (MatchCommentarySession $s): bool => ! $s->isEnded())
                    ->requiresConfirmation()
                    ->modalHeading('Take this commentary off air?')
                    ->modalDescription('Every listener is disconnected now. The commentator is told Haraan took them off air. They can go on air again unless their plan or the rules stop them.')
                    ->action(function (MatchCommentarySession $s): void {
                        app(MatchCommentary::class)->end($s, 'admin', auth()->user());
                        AdminAction::log('commentary.taken_off_air', [
                            'session_id' => $s->id, 'match_id' => $s->match_id, 'commentator' => $s->user_id,
                            'listeners_peak' => $s->peak_listeners, 'reports' => (int) $s->reports_count,
                        ], $s);
                        Notification::make()->success()->title('Taken off air')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMatchCommentarySessions::route('/'),
        ];
    }

    private static function isLive(MatchCommentarySession $s): bool
    {
        return ! $s->isEnded()
            && ($s->last_heartbeat_at ?? $s->started_at)?->gte(now()->subSeconds(MatchCommentary::STALE_AFTER_SECONDS));
    }

    private static function duration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h > 0 ? "{$h}h {$m}m" : ($m > 0 ? "{$m}m" : $seconds.'s');
    }
}
