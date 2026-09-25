<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\ResetPasswordAction;
use App\Filament\Resources\AppUsers\Schemas\UserInfolist;
use App\Filament\Resources\Users\UserResource;
use App\Models\AdminAction;
use App\Models\User;
use App\Support\JwtService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $operator = auth()->user();
        $target = $this->getRecord();

        if ($operator && $target && $operator->id !== $target->id && UserInfolist::canViewPii($target)) {
            AdminAction::log('user.pii_viewed', [
                'channel' => 'view_staff_user_page',
                'accessed_fields' => ['email', 'phone', 'date_of_birth'],
            ], $target);
        }
    }

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return (string) ($record->name ?: 'Staff member');
    }

    protected function getHeaderActions(): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        return [
            EditAction::make(),

            Action::make('toggleStatus')
                ->label(fn (): string => $this->isActive() ? 'Suspend account' : 'Reactivate account')
                ->icon(fn (): string => $this->isActive() ? 'heroicon-m-no-symbol' : 'heroicon-m-check-circle')
                ->color(fn (): string => $this->isActive() ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => $this->isActive() ? 'Suspend staff account?' : 'Reactivate staff account?')
                ->modalDescription(fn (): string => $this->isActive()
                    ? 'Suspend this staff account? Their active sessions will be revoked immediately and they will be barred from /control.'
                    : 'Reactivate this staff account and restore access?')
                ->action(function (): void {
                    $target = $this->getRecord();
                    $suspending = $this->isActive();
                    $newStatus = $suspending ? 'SUSPENDED' : 'ACTIVE';

                    $target->status = $newStatus;
                    $target->save();

                    AdminAction::log(
                        $suspending ? 'user.suspended' : 'user.reactivated',
                        ['status' => $newStatus, 'token_version' => $target->token_version],
                        $target
                    );

                    Notification::make()
                        ->title($suspending ? 'Staff account suspended & sessions revoked' : 'Staff account reactivated')
                        ->success()
                        ->send();
                }),

            ResetPasswordAction::make(),

            Action::make('revokeSessions')
                ->label('Revoke all sessions')
                ->icon('heroicon-m-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revoke all active sessions?')
                ->modalDescription('This will invalidate all active sessions for this account across devices and consoles.')
                ->modalSubmitActionLabel('Revoke all now')
                ->action(function () use ($record): void {
                    $newVersion = JwtService::revokeAllFor($record);

                    AdminAction::log('user.sessions_revoked', ['token_version' => $newVersion], $record);

                    Notification::make()
                        ->title('All sessions revoked')
                        ->body("Token version moved to v{$newVersion}.")
                        ->success()
                        ->send();
                }),
        ];
    }

    private function isActive(): bool
    {
        $record = $this->getRecord();

        return $record instanceof User && $record->isAccountActive();
    }
}
