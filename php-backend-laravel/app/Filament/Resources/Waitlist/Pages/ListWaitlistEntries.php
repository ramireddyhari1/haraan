<?php

declare(strict_types=1);

namespace App\Filament\Resources\Waitlist\Pages;

use App\Filament\Resources\Waitlist\WaitlistEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Builder;

class ListWaitlistEntries extends ListRecords
{
    protected static string $resource = WaitlistEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add to waitlist')
                ->mutateDataUsing(function (array $data): array {
                    $data['created_by'] = auth()->id();

                    return $data;
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'offered' => Tab::make('Offered')
                ->icon('heroicon-m-bell-alert')
                ->badge(WaitlistEntryResource::getEloquentQuery()
                    ->where('status', WaitlistEntry::STATUS_OFFERED)->count() ?: null)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', WaitlistEntry::STATUS_OFFERED)),

            'waiting' => Tab::make('Waiting')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', WaitlistEntry::STATUS_WAITING)),

            'recovered' => Tab::make('Recovered')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('status', WaitlistEntry::STATUS_CONVERTED)),

            'all' => Tab::make('All'),
        ];
    }

    /** Open on the live offers when there are any — those have a clock running. */
    public function getDefaultActiveTab(): string|int|null
    {
        return WaitlistEntryResource::getEloquentQuery()
            ->where('status', WaitlistEntry::STATUS_OFFERED)->exists() ? 'offered' : 'waiting';
    }
}
