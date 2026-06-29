<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AdminAction;
use App\Models\AppSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Branding / theme console. Edits the `branding` group of app_settings, which is
 * surfaced to the app via GET /api/config → "theme". Lets the team restyle and
 * re-copy the app with no release. Super-admins only.
 */
class BrandingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.branding-settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $title = 'Branding & theme';

    /** Branding keys managed here (all stored under the 'branding' group). */
    public const KEYS = ['app_name', 'tagline', 'primary_color', 'accent_color', 'logo', 'support_whatsapp'];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $values = AppSetting::group('branding');
        $this->form->fill(array_merge(array_fill_keys(self::KEYS, null), $values));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_name')->label('App name')->maxLength(50),
                TextInput::make('tagline')->label('Tagline')->maxLength(120),
                ColorPicker::make('primary_color')->label('Primary color'),
                ColorPicker::make('accent_color')->label('Accent color'),
                FileUpload::make('logo')
                    ->label('Logo')
                    ->image()
                    ->disk('public')
                    ->directory('branding')
                    ->helperText('Shown on the app home / splash.'),
                TextInput::make('support_whatsapp')->label('Support WhatsApp')->tel()->maxLength(20),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (self::KEYS as $key) {
            AppSetting::set($key, $state[$key] ?? null, 'branding');
        }

        AdminAction::log('branding.updated', ['keys' => self::KEYS]);
        Notification::make()->title('Branding saved')->success()->send();
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
