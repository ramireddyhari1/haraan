<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use App\Support\PartnerAccountResolver;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePartner extends CreateRecord
{
    protected static string $resource = PartnerResource::class;

    /** Set when this save upgraded an existing member rather than inserting a row. */
    private bool $upgradedExistingMember = false;

    /**
     * Force the PARTNER role. The password is handled in handleRecordCreation()
     * instead of here, because "blank" means two different things: a random password
     * for a brand-new account, but *keep the existing one* when upgrading a member.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = 'PARTNER';

        return $data;
    }

    /**
     * Create the partner — or upgrade the member who already owns this email.
     *
     * The public site and the partner console are two doors into one `users` table
     * (see {@see PartnerAccountResolver}), so an email that already exists cannot be
     * given a second row. In practice the owner of that row is nearly always the same
     * human the admin is trying to onboard, so grant them the PARTNER role in place:
     * they keep their id, bookings, wallet and history, and every foreign key that
     * already points at them stays valid.
     *
     * The form's validation rule has already rejected the cases this must not touch
     * (an existing partner, an internal staff login).
     */
    protected function handleRecordCreation(array $data): Model
    {
        [$partner, $this->upgradedExistingMember] = PartnerAccountResolver::upgradeOrCreate(
            $data,
            $data['password'] ?? null,
        );

        return $partner;
    }

    /**
     * Say plainly which of the two things just happened — an admin who typed a known
     * email needs to know the member was upgraded rather than duplicated, and that
     * their sign-in page has moved.
     */
    protected function getCreatedNotification(): ?Notification
    {
        if (! $this->upgradedExistingMember) {
            return parent::getCreatedNotification();
        }

        return Notification::make()
            ->success()
            ->title('Existing member upgraded to a partner')
            ->body('Their bookings and history carried over. They now sign in at /partner/login with the same email.');
    }
}
