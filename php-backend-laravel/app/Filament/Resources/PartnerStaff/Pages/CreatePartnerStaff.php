<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerStaff\Pages;

use App\Filament\Resources\PartnerStaff\PartnerStaffResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

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

        return $data;
    }
}
