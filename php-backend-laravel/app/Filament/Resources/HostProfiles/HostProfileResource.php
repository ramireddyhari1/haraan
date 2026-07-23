<?php

declare(strict_types=1);

namespace App\Filament\Resources\HostProfiles;

use App\Filament\Resources\HostProfiles\Pages\ListHostProfiles;
use App\Filament\Support\AvatarColumn;
use App\Models\HostProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Admin view of organiser public profiles (Phase 3) — its one job is granting the
 * verified ✓ (which the public /host page already renders). Super-admins only.
 */
class HostProfileResource extends Resource
{
    protected static ?string $model = HostProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Host profiles';

    protected static ?string $modelLabel = 'host profile';

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                AvatarColumn::make('logo', fn (HostProfile $r): ?string => $r->display_name, fn (HostProfile $r): ?string => $r->logo_path),
                TextColumn::make('display_name')
                    ->weight('bold')
                    ->description(fn (HostProfile $r): string => '/host/' . $r->slug)
                    ->searchable(['display_name', 'slug']),
                TextColumn::make('user.email')->label('Owner')->color('gray')->searchable(),
                TextColumn::make('followers')
                    ->label('Followers')
                    ->getStateUsing(fn (HostProfile $r): int => $r->followersCount()),
                IconColumn::make('is_public')->label('Public')->boolean(),
                IconColumn::make('verified')
                    ->label('Verified')
                    ->boolean()
                    ->getStateUsing(fn (HostProfile $r): bool => $r->isVerified()),
                TextColumn::make('created_at')->label('Created')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('toggleVerify')
                    ->label(fn (HostProfile $r): string => $r->isVerified() ? 'Unverify' : 'Verify')
                    ->icon(fn (HostProfile $r): BackedEnum => $r->isVerified() ? Heroicon::OutlinedXCircle : Heroicon::OutlinedCheckBadge)
                    ->color(fn (HostProfile $r): string => $r->isVerified() ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (HostProfile $r): string => $r->isVerified()
                        ? 'Remove the verified badge from this organiser.'
                        : 'Grant the verified ✓ — it shows on their public page.')
                    ->action(function (HostProfile $r): void {
                        $r->verified_at = $r->isVerified() ? null : now();
                        $r->save();
                        Notification::make()
                            ->title($r->isVerified() ? 'Verified' : 'Unverified')
                            ->success()
                            ->send();
                    }),
                Action::make('view')
                    ->label('View page')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (HostProfile $r): string => url('/host/' . $r->slug), shouldOpenInNewTab: true)
                    ->visible(fn (HostProfile $r): bool => $r->isLive()),
            ])
            ->emptyStateHeading('No host profiles yet')
            ->emptyStateIcon(Heroicon::OutlinedIdentification);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHostProfiles::route('/'),
        ];
    }
}
