<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\EditsPlatformRules;
use App\Support\PlatformRules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;

/**
 * Platform → App updates. The in-app update sheet of the Haraan and Partner Android apps:
 * latest and minimum versions, force update, the APK links, the message and "What's new".
 *
 * Served by GET /api/app/update; the minimum versions are also enforced on the server
 * (EnforcePlatformOperations answers 426 to an app below them).
 */
class AppUpdatesPage extends Page implements HasForms
{
    use EditsPlatformRules;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'App updates';

    protected static ?string $title = 'App updates';

    protected static ?string $slug = 'app-updates';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.membership-settings';

    private const SECTIONS = ['app', 'partner_app'];

    public static function canAccess(): bool
    {
        foreach (self::SECTIONS as $section) {
            if (self::canManageSection($section)) {
                return true;
            }
        }

        return false;
    }

    protected function ruleSections(): array
    {
        return self::SECTIONS;
    }

    public function getSubheading(): ?string
    {
        return 'Upload the new APK to its link first, then raise the latest version. Phones check when the app opens '
            .'and when it comes back to the front, and see the update sheet straight away.';
    }

    public function mount(): void
    {
        $this->fillRules();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->ruleSectionComponents())->statePath('data');
    }

    public function save(): void
    {
        $this->saveRules('app_updates.updated');
    }

    /** Fetch each APK link's headers, so a typo or a missing file shows up before a phone hits it. */
    public function checkLinks(): void
    {
        $lines = [];
        $ok = true;
        foreach (['Haraan app' => 'app.apk_url', 'Partner app' => 'partner_app.apk_url'] as $name => $key) {
            $url = PlatformRules::string($key);
            try {
                $res = Http::timeout(8)->withOptions(['allow_redirects' => true])->head($url);
                $bytes = (int) $res->header('Content-Length');
                $type = (string) $res->header('Content-Type');
                if ($res->successful()) {
                    $size = $bytes > 0 ? number_format($bytes / 1048576, 1).' MB' : 'size unknown';
                    $lines[] = "{$name}: reachable, {$size}".($type !== '' ? " ({$type})" : '');
                } else {
                    $ok = false;
                    $lines[] = "{$name}: HTTP {$res->status()} at {$url}";
                }
            } catch (\Throwable) {
                $ok = false;
                $lines[] = "{$name}: could not be reached at {$url}";
            }
        }

        Notification::make()
            ->title($ok ? 'Both APK links work' : 'An APK link has a problem')
            ->body(implode("\n", $lines))
            ->{$ok ? 'success' : 'danger'}()
            ->persistent()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkLinks')->label('Check APK links')->icon('heroicon-m-link')->color('gray')->action('checkLinks'),
            Action::make('save')
                ->label('Save changes')
                ->icon('heroicon-m-check')
                ->requiresConfirmation()
                ->modalHeading('Publish to phones now?')
                ->modalDescription('Apps check when they open. A raised latest version shows the update sheet; a minimum version blocks older apps until they update.')
                ->modalSubmitActionLabel('Publish')
                ->action('save'),
        ];
    }
}
