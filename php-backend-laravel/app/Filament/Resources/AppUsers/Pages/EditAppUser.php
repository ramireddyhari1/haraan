<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\Pages;

use App\Filament\Actions\EraseAccountAction;
use App\Filament\Actions\ResetPasswordAction;
use App\Filament\Resources\AppUsers\AppUserResource;
use Filament\Resources\Pages\EditRecord;

class EditAppUser extends EditRecord
{
    protected static string $resource = AppUserResource::class;

    /**
     * No DeleteAction here, deliberately — see {@see EraseAccountAction} for why a hard
     * delete would take this person's bookings and payments with them.
     */
    protected function getHeaderActions(): array
    {
        return [
            ResetPasswordAction::make(),
            EraseAccountAction::make()
                ->visible(fn (): bool => AppUserResource::canDelete($this->getRecord()))
                ->after(fn () => $this->redirect(AppUserResource::getUrl('index'))),
        ];
    }
}
