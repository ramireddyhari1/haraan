<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\EraseAccountAction;
use App\Filament\Actions\ResetPasswordAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * No DeleteAction, for the same reason as the app-user page: a staff row is a `users`
     * row, and `bookings.user_id` cascades. A staff member who has ever bought a ticket
     * (the seed admin on this database has) would take their own payment records with
     * them. Erasing anonymises instead and keeps the books intact.
     */
    protected function getHeaderActions(): array
    {
        return [
            ResetPasswordAction::make(),
            EraseAccountAction::make()
                ->visible(fn (): bool => UserResource::canDelete($this->getRecord()))
                ->after(fn () => $this->redirect(UserResource::getUrl('index'))),
        ];
    }
}
