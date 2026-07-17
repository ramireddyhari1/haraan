<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\EventAnalytics;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\RelationManagers\TicketTypesRelationManager;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Filament\Resources\Events\Tables\EventsTable;
use App\Filament\Concerns\ScopesToOrganization;
use App\Models\Event;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EventResource extends Resource
{
    use ScopesToOrganization;

    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $cluster = \App\Filament\Clusters\Events\EventsCluster::class;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('events') ?? false;
    }

    protected static ?string $recordTitleAttribute = 'title';

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'city'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'City' => $record->city,
            'Status' => $record->status ? ucfirst(strtolower((string) $record->status)) : null,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Ticket tiers (Kid/Adult/Couple/… ) for the event — add, edit prices, delete.
            TicketTypesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            // Clicking an event name opens this read-only analytics dashboard (see EventsTable
            // ->recordUrl); editing is a deliberate, separate action via the Edit button.
            'analytics' => EventAnalytics::route('/{record}/analytics'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
