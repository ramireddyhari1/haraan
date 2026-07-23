<?php

namespace App\Filament\Resources\LoginPosters;

use App\Filament\Resources\LoginPosters\Pages\CreateLoginPoster;
use App\Filament\Resources\LoginPosters\Pages\EditLoginPoster;
use App\Filament\Resources\LoginPosters\Pages\ListLoginPosters;
use App\Filament\Resources\LoginPosters\Schemas\LoginPosterForm;
use App\Filament\Resources\LoginPosters\Tables\LoginPostersTable;
use App\Models\Ad;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Manages the full-bleed poster carousel on the app's login screen.
 *
 * Login posters are stored as {@see Ad} rows with placement 'login_poster'; the public
 * /api/login-posters endpoint serves the active ones (ordered by sort_order) to the Android
 * app, which shows them behind the login card and falls back to its bundled drawables when
 * none are configured. This resource is a purpose-built lane over that same table so an admin
 * never has to touch the generic Ads list or know the magic placement string.
 */
class LoginPosterResource extends Resource
{
    protected static ?string $model = Ad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    // Top-level "App Content" section (its own sidebar header), NOT buried in the Marketing
    // cluster — login posters are the app's first impression, grouped with the other
    // "what the app displays" controls like Home layout.
    protected static string|\UnitEnum|null $navigationGroup = 'App Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Login Posters';

    protected static ?string $modelLabel = 'login poster';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    /** Only ever surface login-poster rows — the Ads resource owns every other placement. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('placement', 'login_poster');
    }

    /**
     * Fold the transient `image_url` field into the real `image` column before saving.
     * An uploaded file wins; otherwise the pasted URL is used. Exactly one must be present.
     * Called from the Create and Edit pages' mutate hooks.
     */
    public static function foldImageUrl(array $data): array
    {
        // `ads.title` is NOT NULL; Title is optional on this form, so coerce a blank to ''
        // (the app never displays it — it's just an admin-side label).
        $data['title'] = (string) ($data['title'] ?? '');

        $url = trim((string) ($data['image_url'] ?? ''));
        unset($data['image_url']);

        if (empty($data['image']) && $url !== '') {
            $data['image'] = $url;
        }

        if (empty($data['image'])) {
            // statePath is 'data' on Filament resource forms, so key the error at 'data.image'
            // for it to attach to the upload field.
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.image' => 'Add a poster image — upload a file or paste an image URL.',
            ]);
        }

        return $data;
    }

    /**
     * When editing a poster whose `image` is an absolute URL (pasted, or created via the old
     * Blade admin), show it in the `image_url` field and leave the file upload empty — the
     * FileUpload component only understands disk paths, not remote URLs.
     */
    public static function splitImageUrlForEdit(array $data): array
    {
        if (! empty($data['image']) && str_starts_with((string) $data['image'], 'http')) {
            $data['image_url'] = $data['image'];
            $data['image'] = null;
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return LoginPosterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoginPostersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoginPosters::route('/'),
            'create' => CreateLoginPoster::route('/create'),
            'edit' => EditLoginPoster::route('/{record}/edit'),
        ];
    }
}
