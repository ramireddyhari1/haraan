<?php

namespace App\Filament\Resources\PlayerReports;

use App\Filament\Resources\PlayerReports\Pages\ListPlayerReports;
use App\Filament\Resources\PlayerReports\Tables\PlayerReportsTable;
use App\Models\PlayerReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * The moderation queue behind the app's Report action.
 *
 * A report is a message to a human, so this resource is a WORKLIST, not a CRUD
 * screen: nothing is created here, nothing is edited by hand. A moderator reads a
 * row and either dismisses it or actions it, and both write who decided and when.
 */
class PlayerReportResource extends Resource
{
    protected static ?string $model = PlayerReport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Player reports';

    protected static ?string $modelLabel = 'player report';

    protected static ?int $navigationSort = 21;

    /**
     * Reports name both an accuser and an accused. Same audience as Support — the ops
     * team and super-admins, never a department manager and never a partner.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isSuperAdmin() || $user->hasRoleEither(['OPS']));
    }

    /** Reports only ever originate in the app. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** The badge counts what is actually waiting on a person. */
    public static function getNavigationBadge(): ?string
    {
        $count = PlayerReport::query()->where('status', 'open')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return PlayerReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlayerReports::route('/'),
        ];
    }
}
