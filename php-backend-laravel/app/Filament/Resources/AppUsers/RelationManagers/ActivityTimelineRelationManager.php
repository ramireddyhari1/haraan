<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\RelationManagers;

use App\Models\AdminAction;
use App\Models\User;
use App\Services\UserTimelineService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Handcrafted Unified Activity Timeline for User 360.
 *
 * Synthesizes real historical events across the user's entire journey into
 * an interactive, chronological activity stream with category pills,
 * search filtering, and inspection links.
 */
class ActivityTimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'adminActionsReceived';

    protected static ?string $title = 'Unified Activity Timeline';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-clock';

    public string $category = 'all';

    public string $search = '';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var User $ownerRecord */
        $total = $ownerRecord->adminActionsReceived()->count()
            + (int) ($ownerRecord->bookings_count ?? 0)
            + (int) ($ownerRecord->matches_played_count ?? 0);

        return $total > 0 ? (string) $total : null;
    }

    public function render(): View
    {
        /** @var User $user */
        $user = $this->getOwnerRecord();

        $timeline = UserTimelineService::getTimelineFor(
            user: $user,
            category: $this->category,
            search: $this->search,
            limit: 60,
            viewer: auth()->user()
        );

        return view('filament.resources.app-users.relation-managers.unified-activity-timeline', [
            'timeline' => $timeline,
            'category' => $this->category,
            'search'   => $this->search,
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Event / Action')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'suspended'), str_contains($state, 'revoked') => 'danger',
                        str_contains($state, 'verified'), str_contains($state, 'created') => 'success',
                        str_contains($state, 'password'), str_contains($state, 'pii') => 'warning',
                        default => 'info',
                    })
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Operator')
                    ->placeholder('System')
                    ->description(fn (AdminAction $r): ?string => $r->user?->email),

                TextColumn::make('meta')
                    ->label('Audit details')
                    ->state(fn (AdminAction $r): string => $r->summary())
                    ->wrap()
                    ->placeholder('—'),

                TextColumn::make('ip')
                    ->label('IP address')
                    ->placeholder('—'),
            ]);
    }
}
