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
