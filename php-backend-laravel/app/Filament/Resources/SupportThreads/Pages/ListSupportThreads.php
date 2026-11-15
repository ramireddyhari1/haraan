<?php

namespace App\Filament\Resources\SupportThreads\Pages;

use App\Filament\Resources\SupportThreads\SupportThreadResource;
use App\Models\SupportThread;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListSupportThreads extends ListRecords
{
    protected static string $resource = SupportThreadResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** Inbox header: a title and one true line about who is waiting. */
    public function getHeader(): ?View
    {
        return view('filament.resources.support-threads.list-header');
    }

    /** The inbox views an agent switches between, one tap each. */
    public function getTabs(): array
    {
        $count = fn (callable $scope): ?int => ($n = $scope(SupportThread::query())->count()) > 0 ? $n : null;

        $waiting = fn (Builder $q) => $q->where('admin_unread_count', '>', 0);
        $mine = fn (Builder $q) => $q->where('assigned_to', auth()->id())->where('status', '!=', 'closed');
        $open = fn (Builder $q) => $q->where('status', 'open');
        $pending = fn (Builder $q) => $q->where('status', 'pending');
        $closed = fn (Builder $q) => $q->where('status', 'closed');

        return [
            'all' => Tab::make('All'),
            'waiting' => Tab::make('Needs reply')
                ->icon('heroicon-m-bell-alert')
                ->badge($count($waiting))
                ->badgeColor('danger')
                ->modifyQueryUsing($waiting),
            'mine' => Tab::make('Mine')
                ->badge($count($mine))
                ->badgeColor('gray')
                ->modifyQueryUsing($mine),
            'open' => Tab::make('Open')
                ->badge($count($open))
                ->badgeColor('gray')
                ->modifyQueryUsing($open),
            'pending' => Tab::make('Pending')
                ->badge($count($pending))
                ->badgeColor('gray')
                ->modifyQueryUsing($pending),
            'closed' => Tab::make('Closed')
                ->modifyQueryUsing($closed),
        ];
    }
}
