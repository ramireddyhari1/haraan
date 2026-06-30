<?php

namespace App\Filament\Clusters\Events\Pages;

use App\Filament\Clusters\Events\EventsCluster;
use App\Filament\Clusters\Events\Widgets\EventsStatsWidget;
use BackedEnum;
use Filament\Pages\Page;

class EventsOverview extends Page
{
    protected static ?string $cluster = EventsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'Events Overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.clusters.events.events-overview';

    protected function getHeaderWidgets(): array
    {
        return [
            EventsStatsWidget::class,
        ];
    }
}
