<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueCandidates\Pages;

use App\Filament\Clusters\GameHub\Pages\VenueDiscoverySettings;
use App\Filament\Resources\VenueCandidates\VenueCandidateResource;
use App\Models\AdminAction;
use App\Models\VenueCandidate;
use App\Services\VenueDiscovery\DiscoverySettings;
use App\Services\VenueDiscovery\VenueDiscovery;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class ListVenueCandidates extends ListRecords
{
    protected static string $resource = VenueCandidateResource::class;

    public function getSubheading(): ?string
    {
        return 'Venues found on Google that are not on Haraan yet. Listing one gives it a website page with booking off.';
    }

    protected function getHeaderActions(): array
    {
        $cities = DiscoverySettings::cities();
        $perCity = count(DiscoverySettings::terms()) * DiscoverySettings::pages();

        return [
            Action::make('settings')
                ->label('Settings')
                ->icon('heroicon-m-cog-6-tooth')
                ->color('gray')
                ->url(VenueDiscoverySettings::getUrl()),
            Action::make('recheck')
                ->label('Check Playo again')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => VenueDiscovery::crossCheckEnabled())
                ->schema([
                    Select::make('city')->label('City')->options(fn (): array => VenueCandidate::query()
                        ->whereNotNull('city')->distinct()->orderBy('city')->pluck('city', 'city')->all())->required()->native(false),
                ])
                ->modalDescription('Reads Playo’s public venue list for the city and marks which found venues are on it. No Google searches are used.')
                ->modalSubmitActionLabel('Check')
                ->action(function (array $data): void {
                    try {
                        $c = app(VenueDiscovery::class)->crossCheck((string) $data['city']);
                        Notification::make()
                            ->title($c['playo'] === 0 ? 'Playo lists nothing in ' . $data['city'] : $c['matched'] . ' of ' . $c['checked'] . ' are also on Playo')
                            ->body($c['playo'] === 0 ? 'Or its page could not be read just now. Nothing was changed.' : null)
                            ->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Could not check Playo')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('find')
                ->label('Find venues')
                ->icon('heroicon-m-magnifying-glass')
                ->schema([
                    Select::make('city')->label('City')->options(array_combine($cities, $cities))->required()->native(false)->searchable(),
                ])
                ->modalDescription('Searches Google for sports venues in the city — up to ' . $perCity . ' paid searches — then checks each one against Playo. Venues already found are updated, never duplicated. This takes up to a minute.')
                ->modalSubmitActionLabel('Search')
                ->action(function (array $data): void {
                    @set_time_limit(180);
                    $city = (string) $data['city'];
                    try {
                        $discovery = app(VenueDiscovery::class);
                        $s = $discovery->harvest($city);
                        $c = VenueDiscovery::crossCheckEnabled() ? $discovery->crossCheck($city) : null;
                        AdminAction::log('venue_discovery.searched', ['city' => $city] + $s);

                        Notification::make()
                            ->title($s['added'] . ' new ' . ($s['added'] === 1 ? 'venue' : 'venues') . ' found in ' . $city)
                            ->body(implode(' ', array_filter([
                                $s['updated'] . ' were already in this list.',
                                $s['duplicates'] > 0 ? $s['duplicates'] . ' are already on Haraan.' : null,
                                $s['skipped'] . ' results were not sports venues.',
                                $c !== null && $c['playo'] > 0 ? $c['matched'] . ' are also on Playo.' : null,
                            ])))
                            ->success()->persistent()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Search did not finish')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }

    public function getTabs(): array
    {
        $count = fn (string $status): ?int => VenueCandidate::query()->where('status', $status)->count() ?: null;
        $onPlayo = fn (Builder $query): Builder => $query
            ->where('status', VenueCandidate::STATUS_NEW)
            ->where('crosscheck_status', VenueCandidate::CHECK_MATCHED);

        return [
            'review' => Tab::make('To review')
                ->badge($count(VenueCandidate::STATUS_NEW))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', VenueCandidate::STATUS_NEW)),
            'playo' => Tab::make('Also on Playo')
                ->badge($onPlayo(VenueCandidate::query())->count() ?: null)
                ->badgeColor('success')
                ->modifyQueryUsing($onPlayo),
            'listed' => Tab::make('Listed')
                ->badge($count(VenueCandidate::STATUS_APPROVED))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', VenueCandidate::STATUS_APPROVED)),
            'duplicate' => Tab::make('Already on Haraan')
                ->badge($count(VenueCandidate::STATUS_DUPLICATE))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', VenueCandidate::STATUS_DUPLICATE)),
            'rejected' => Tab::make('Rejected')
                ->badge($count(VenueCandidate::STATUS_REJECTED))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', VenueCandidate::STATUS_REJECTED)),
        ];
    }
}
