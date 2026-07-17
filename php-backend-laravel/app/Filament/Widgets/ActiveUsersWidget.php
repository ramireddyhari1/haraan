<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/**
 * "Who's on the app right now" — the most-recently-active users, newest heartbeat first.
 * Driven by users.last_seen_at, stamped (throttled) by the JWT middleware on every
 * authenticated API request. Read-only glance; deeper cuts live in the Users resource.
 */
class ActiveUsersWidget extends TableWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    protected static ?int $sort = -20;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return 'Active users';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereNotNull('last_seen_at')
                    ->orderByDesc('last_seen_at')
                    ->limit(10)
            )
            ->paginated(false)
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Signed-in users will appear here as they open the app.')
            ->emptyStateIcon('heroicon-o-users')
            ->columns([
                TextColumn::make('name')
                    ->label('User')
                    ->weight('bold')
                    ->description(fn (User $r): ?string => $r->email ?? $r->phone)
                    ->placeholder('Unnamed')
                    ->searchable(),
                TextColumn::make('presence')
                    ->label('Status')
                    ->badge()
                    ->state(fn (User $r): string => $this->presenceLabel($r->last_seen_at))
                    ->color(fn (User $r): string => $this->presenceColor($r->last_seen_at)),
                TextColumn::make('district')
                    ->label('District')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('role')
                    ->badge()
                    ->placeholder('—')
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (User $r): ?string => $r->last_seen_at?->format('d M Y, H:i')),
            ]);
    }

    private function presenceLabel(?Carbon $seen): string
    {
        if ($seen === null) {
            return 'Away';
        }
        if ($seen->gt(now()->subMinutes(5))) {
            return 'Online';
        }
        if ($seen->gt(now()->subDay())) {
            return 'Today';
        }
        if ($seen->gt(now()->subDays(7))) {
            return 'This week';
        }

        return 'Away';
    }

    private function presenceColor(?Carbon $seen): string
    {
        return match ($this->presenceLabel($seen)) {
            'Online' => 'success',
            'Today' => 'info',
            'This week' => 'warning',
            default => 'gray',
        };
    }
}
