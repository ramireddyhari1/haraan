<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\RelationManagers;

use App\Models\AdminAction;
use App\Models\MemberDevice;
use App\Models\User;
use App\Services\Membership\MemberDevices;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * User 360 → Signed-in devices: every phone and browser this member is (or was) signed in
 * on, against their plan's device limit. Admins can sign one device out, or all of them.
 * The limit itself is a plan entitlement (Member plans) or a per-member override.
 */
class SignedInDevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'memberDevices';

    protected static ?string $title = 'Signed-in devices';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-device-tablet';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var User $ownerRecord */
        $live = $ownerRecord->memberDevices()
            ->live(\App\Support\Membership\MembershipSettings::int('device_idle_days'))
            ->where('status', MemberDevice::STATUS_ACTIVE)
            ->count();
        $limit = app(MemberDevices::class)->limitFor($ownerRecord);

        return $live === 0 ? null : $live.' / '.($limit ?? '∞');
    }

    public function table(Table $table): Table
    {
        $canManage = fn (): bool => auth()->user()?->canManage('admin') ?? false;

        return $table
            ->description(function (): string {
                /** @var User $user */
                $user = $this->getOwnerRecord();
                $limit = app(MemberDevices::class)->limitFor($user);

                return 'Device limit for this member: '.($limit === null ? 'unlimited' : $limit)
                    .'. Change it per plan under Member plans, or for this member with an entitlement override (account.devices).';
            })
            ->defaultSort('last_active_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('name')
                    ->label('Device')
                    ->description(fn (MemberDevice $r): string => ucfirst($r->platform).($r->app_version ? ' · v'.$r->app_version : ''))
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(function (MemberDevice $r): string {
                        if ($r->isRevoked()) {
                            return 'Signed out';
                        }
                        if (app(MemberDevices::class)->hasLapsed($r)) {
                            return 'Expired';
                        }

                        return $r->isPending() ? 'Waiting for a slot' : 'Signed in';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Signed in' => 'success',
                        'Waiting for a slot' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('last_active_at')->label('Last active')->since()->sortable(),
                TextColumn::make('signed_in_at')->label('Signed in')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('revoked_reason')
                    ->label('Signed out by')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        MemberDevice::REASON_SIGNED_OUT => 'Member (on the device)',
                        MemberDevice::REASON_REMOVED => 'Member (from another device)',
                        MemberDevice::REASON_ADMIN => 'Admin',
                        default => (string) $state,
                    })
                    ->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('signed_in')
                    ->label('Signed in only')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->where('status', '!=', MemberDevice::STATUS_REVOKED),
                        false: fn (Builder $query) => $query->where('status', MemberDevice::STATUS_REVOKED),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->headerActions([
                Action::make('signOutEverywhere')
                    ->label('Sign out everywhere')
                    ->icon('heroicon-m-arrow-right-on-rectangle')
                    ->color('danger')
                    ->visible($canManage)
                    ->requiresConfirmation()
                    ->modalDescription('Ends every app and website session this member has, including ones from before device limits. They sign in again as normal.')
                    ->action(function (): void {
                        /** @var User $user */
                        $user = $this->getOwnerRecord();
                        $count = app(MemberDevices::class)->revokeAll($user, MemberDevice::REASON_ADMIN, auth()->user());
                        AdminAction::log('member_device.revoked_all', ['devices' => $count], $user);
                        Notification::make()->title('Signed out everywhere')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('signOut')
                    ->label('Sign out')
                    ->icon('heroicon-m-arrow-right-on-rectangle')
                    ->color('danger')
                    ->visible(fn (MemberDevice $r): bool => $canManage() && ! $r->isRevoked())
                    ->requiresConfirmation()
                    ->modalDescription(fn (MemberDevice $r): string => "{$r->name} is signed out straight away and its slot frees up.")
                    ->action(function (MemberDevice $record): void {
                        app(MemberDevices::class)->revokeByAdmin($record, auth()->user());
                        Notification::make()->title("Signed out {$record->name}")->success()->send();
                    }),
            ]);
    }
}
