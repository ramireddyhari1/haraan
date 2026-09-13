<?php

declare(strict_types=1);

namespace App\Filament\Resources\SectionThemes;

use App\Filament\Resources\SectionThemes\Pages\CreateSectionTheme;
use App\Filament\Resources\SectionThemes\Pages\EditSectionTheme;
use App\Filament\Resources\SectionThemes\Pages\ListSectionThemes;
use App\Filament\Resources\SectionThemes\Schemas\SectionThemeForm;
use App\Filament\Resources\SectionThemes\Tables\SectionThemesTable;
use App\Models\SectionTheme;
use App\Support\SectionThemeJson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Campaign themes for the app's Events and Pulse lanes: header colours, an optional
 * decoration (image or Lottie) and a start/end window. Themes can also be imported and
 * exported as one JSON file ({@see SectionThemeJson}). Changes reach running apps live over
 * Reverb (domain "themes"); with nothing live the app keeps its normal look.
 */
class SectionThemeResource extends Resource
{
    protected static ?string $model = SectionTheme::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|\UnitEnum|null $navigationGroup = 'App Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Campaign Themes';

    protected static ?string $modelLabel = 'campaign theme';

    protected static ?string $recordTitleAttribute = 'campaign_name';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    /**
     * Fold the transient `decoration_url` into `decoration` (an upload wins), and refuse a
     * .json upload that isn't actually a Lottie animation — the app would silently draw
     * nothing, and the admin would never know why.
     */
    public static function foldDecoration(array $data): array
    {
        $url = trim((string) ($data['decoration_url'] ?? ''));
        unset($data['decoration_url']);

        if (empty($data['decoration']) && $url !== '') {
            if (preg_match('/\.(png|webp|json)(\?.*)?$/i', $url) !== 1) {
                throw ValidationException::withMessages([
                    'data.decoration_url' => 'Link to a .png, .webp or Lottie .json file.',
                ]);
            }
            $data['decoration'] = $url;
        }

        $path = (string) ($data['decoration'] ?? '');
        if ($path !== '' && ! str_starts_with($path, 'http')) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $disk = Storage::disk('public');
            $valid = match ($extension) {
                'png', 'webp' => true,
                'json' => SectionThemeJson::isLottie((string) $disk->get($path)),
                default => false,
            };
            if (! $valid) {
                $disk->delete($path);
                throw ValidationException::withMessages([
                    'data.decoration' => $extension === 'json'
                        ? 'That .json file is not a Lottie animation. Export it from After Effects (Bodymovin) or LottieFiles.'
                        : 'Upload a .png, .webp or Lottie .json file.',
                ]);
            }
        }

        $data['decoration'] = $path !== '' ? $path : null;

        return $data;
    }

    /** A linked (absolute URL) decoration shows in the link field; FileUpload only knows disk paths. */
    public static function splitDecorationForEdit(array $data): array
    {
        if (! empty($data['decoration']) && str_starts_with((string) $data['decoration'], 'http')) {
            $data['decoration_url'] = $data['decoration'];
            $data['decoration'] = null;
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return SectionThemeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SectionThemesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSectionThemes::route('/'),
            'create' => CreateSectionTheme::route('/create'),
            'edit' => EditSectionTheme::route('/{record}/edit'),
        ];
    }
}
