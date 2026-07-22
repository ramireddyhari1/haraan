<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerStaff\Pages;

use App\Filament\Resources\PartnerStaff\PartnerStaffResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreatePartnerStaff extends CreateRecord
{
    protected static string $resource = PartnerStaffResource::class;

    /**
     * Stamp the ownership + role a desk person needs, mirroring the
     * /api/partner/staff endpoint: they belong to this owner, inherit its lane,
     * and are PARTNER/ACTIVE like the app expects.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $owner = auth()->user();

        $data['role'] = 'PARTNER';
        $data['status'] = 'ACTIVE';
        $data['partner_type'] = $owner?->partner_type;
        $data['parent_partner_id'] = $owner?->effectivePartnerId();

        // Keep only real capabilities (guards against tampered input).
        $data['staff_permissions'] = array_values(array_intersect(
            $data['staff_permissions'] ?? [],
            User::STAFF_PERMISSIONS,
        ));

        // Inviting: no password was entered — set a random one now so the row is
        // valid; the emailed link lets them choose their own. `send_invite` is a
        // form-only toggle, read from the raw Livewire state.
        if (($this->data['send_invite'] ?? false) && empty($data['password'])) {
            $data['password'] = Hash::make(Str::random(40));
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->data['send_invite'] ?? false) {
            PartnerStaffResource::sendInvite($this->record);

            Notification::make()
                ->title('Invite sent')
                ->body("{$this->record->email} can set their password from the email.")
                ->success()
                ->send();
        }
    }
}
