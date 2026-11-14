<?php

namespace App\Filament\Pages;

use App\Models\AdminAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class Cities extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.cities';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Cities';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public ?array $data = [];

    private function citiesPath(): string
    {
        return public_path('data/cities.json');
    }

    public function mount(): void
    {
        $path = $this->citiesPath();
        $json = is_file($path) ? (string) file_get_contents($path) : '[]';
        $this->form->fill(['cities_json' => $json]);
    }

    /**
     * Every city that has venues on Haraan (bookable or listed), busiest first, with a
     * centre point: Google's geocode of the city (cached 30 days), else the middle of its
     * venues' pins. The page offers the ones missing from the picker as one-click adds,
     * and fills in coordinates for picker cities that have none — a picked city with a
     * point sorts every "near you" list by real distance.
     *
     * @return list<array{name: string, venues: int, lat: ?float, lng: ?float}>
     */
    public function venueCities(): array
    {
        $rows = \App\Models\Venue::query()
            ->where(fn ($q) => $q->published()->orWhere(fn ($l) => $l->listed()))
            ->whereNotNull('city')->where('city', '!=', '')
            ->get(['city', 'latitude', 'longitude']);

        $geo = new \App\Support\MatchGeocoder;

        return $rows
            ->groupBy(fn ($v) => mb_convert_case(trim((string) $v->city), MB_CASE_TITLE))
            ->map(function ($group, string $name) use ($geo): array {
                [$lat, $lng] = $geo->geocode($name . ', India');
                if ($lat === null) {
                    $pinned = $group->filter(fn ($v) => $v->latitude !== null && $v->longitude !== null);
                    $lat = $pinned->isNotEmpty() ? round((float) $pinned->avg('latitude'), 5) : null;
                    $lng = $pinned->isNotEmpty() ? round((float) $pinned->avg('longitude'), 5) : null;
                }

                return ['name' => $name, 'venues' => $group->count(), 'lat' => $lat, 'lng' => $lng];
            })
            ->sortByDesc('venues')
            ->values()
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('cities_json')
                    ->label('cities.json')
                    ->helperText('Drives the city pickers in the app and website. Must be valid JSON.')
                    ->rows(20)
                    ->required()
                    ->extraInputAttributes(['style' => 'font-family: monospace;']),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $decoded = json_decode((string) $state['cities_json'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Notification::make()->title('Invalid JSON')->body(json_last_error_msg())->danger()->send();

            return;
        }

        $dir = dirname($this->citiesPath());
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->citiesPath(),
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        AdminAction::log('cities.updated', ['count' => is_array($decoded) ? count($decoded) : null]);
        Notification::make()->title('Cities saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->icon('heroicon-m-check')
                ->action('save'),
        ];
    }
}
