<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberSubscriptions\RelationManagers;

use App\Models\MemberSubscriptionEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The subscription's timeline: webhooks applied, member actions, admin actions. Read-only. */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Timeline';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('actor'))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, g:i:s A'),
                TextColumn::make('type')->badge()->color('gray'),
                TextColumn::make('transition')
                    ->label('Status')
                    ->state(fn (MemberSubscriptionEvent $r): string => $r->from_status === $r->to_status
                        ? (string) ($r->to_status ?? '—')
                        : ($r->from_status ?? '∅') . ' → ' . ($r->to_status ?? '∅')),
                TextColumn::make('actor.name')->label('By')->placeholder('System'),
                TextColumn::make('provider_event_id')->label('Razorpay event')->placeholder('—')->copyable()->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
