<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\AdminAction;
use App\Models\User;
use App\Services\AccountEraser;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Removing a person, without removing the money.
 *
 * Filament's stock DeleteAction calls $record->delete(), and `bookings.user_id` is declared
 * cascadeOnDelete (see create_bookings_table) — as are fourteen other tables hanging off
 * `users`. Deleting an account through the panel therefore destroyed that person's bookings
 * and booking_payments: the rows that reconcile against Razorpay and that we are required
 * to keep. This is not theoretical for "staff only" either — the seed admin on this very
 * database owns a booking, so no role is safe to hard-delete.
 *
 * {@see AccountEraser} is the supported path: it anonymises the row in place, purges the
 * personal tables, deletes the avatar file and bumps token_version so every live JWT dies.
 * The account number survives so the books still total; the human does not.
 */
final class EraseAccountAction
{
    public static function make(): Action
    {
        return Action::make('erase')
            ->label('Erase account')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Erase this account?')
            ->modalDescription(
                'Everything that identifies this person is removed: name, email, phone, '
                .'profile, devices, support threads and photo. Their bookings and payments '
                .'are KEPT and stay in the books, but no longer point at a person. They are '
                .'signed out everywhere immediately. This cannot be undone.'
            )
            ->modalSubmitActionLabel('Erase account')
            ->form([
                Textarea::make('reason')
                    ->label('Why are you erasing this account?')
                    ->required()
                    ->maxLength(500)
                    ->helperText('Recorded in the audit log against this account.'),
            ])
            ->action(function (array $data, User $record): array {
                $result = app(AccountEraser::class)->erase($record);

                // The eraser writes through the query builder, so no model event fires and
                // AuditsAdminChanges never sees it. Erasing a person is the most
                // consequential thing this panel can do — log it explicitly.
                AdminAction::log('user.erased', [
                    'reason' => $data['reason'],
                    'purged' => $result['purged'],
                ], $record);

                Notification::make()
                    ->success()
                    ->title('Account erased')
                    ->body('Bookings and payments were kept; the person was removed.')
                    ->send();

                return $result;
            });
    }
}
