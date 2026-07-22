<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerStaff\Pages;

use App\Filament\Resources\PartnerStaff\PartnerStaffResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPartnerStaff extends EditRecord
{
    protected static string $resource = PartnerStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resendInvite')
                ->label('Send set-password link')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => "Email a fresh set-password link to {$this->record->email}.")
                ->action(function (): void {
                    PartnerStaffResource::sendInvite($this->record);
                    Notification::make()->title('Invite sent')->success()->send();
                }),
            Action::make('toggleStatus')
                ->label(fn (): string => $this->isSuspended() ? 'Reactivate' : 'Suspend')
                ->icon(fn (): string => $this->isSuspended() ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedNoSymbol)
                ->color(fn (): string => $this->isSuspended() ? 'success' : 'warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->status = $this->isSuspended() ? 'ACTIVE' : 'SUSPENDED';
                    $this->record->save();
                    Notification::make()
                        ->title($this->isSuspended() ? 'Suspended' : 'Reactivated')
                        ->success()
                        ->send();
                }),
            DeleteAction::make()->label('Remove'),
        ];
    }

    private function isSuspended(): bool
    {
        return strtoupper((string) $this->record->status) === 'SUSPENDED';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['staff_permissions'] = array_values(array_intersect(
            $data['staff_permissions'] ?? [],
            User::STAFF_PERMISSIONS,
        ));

        return $data;
    }
}
