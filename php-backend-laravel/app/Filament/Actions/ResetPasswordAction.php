<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\AdminAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;

/**
 * Resetting someone else's password, as an explicit act rather than a form field.
 *
 * This used to be a `password` input sitting in the middle of the shared user form, which
 * meant an operator could take over any member's account by typing into a box and pressing
 * Save — no confirmation, no reason, no notice to the person, and (because `password` was
 * not an audited attribute and was the only field changed) no audit entry at all.
 *
 * Here it is its own action: it states what will happen, demands a reason, records the
 * reset against the account, and ends every session the old credential opened.
 */
final class ResetPasswordAction
{
    public static function make(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset password')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Reset this account\'s password?')
            ->modalDescription(
                'The person will be signed out on every device and will need the new '
                .'password to get back in. Give it to them over a channel you trust — '
                .'it is not emailed to them automatically.'
            )
            ->modalSubmitActionLabel('Reset password')
            ->form([
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(10)
                    ->maxLength(128)
                    ->helperText('At least 10 characters.'),
                Textarea::make('reason')
                    ->label('Why are you resetting it?')
                    ->required()
                    ->maxLength(500)
                    ->helperText('Recorded in the audit log against this account.'),
            ])
            ->action(function (array $data, User $record): void {
                // The User model's saving hook bumps token_version whenever `password`
                // is dirty, so saving here is what signs them out everywhere.
                $record->password = Hash::make($data['password']);
                $record->save();

                // AuditsAdminChanges already logs `user.updated` with password redacted.
                // This second entry carries the operator's reason, which the diff cannot.
                AdminAction::log('user.password_reset', ['reason' => $data['reason']], $record);

                Notification::make()
                    ->success()
                    ->title('Password reset')
                    ->body('Every session for this account has been signed out.')
                    ->send();
            });
    }
}
