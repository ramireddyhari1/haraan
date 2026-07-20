<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerStaff\Pages;

use App\Filament\Resources\PartnerStaff\PartnerStaffResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPartnerStaff extends EditRecord
{
    protected static string $resource = PartnerStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Remove'),
        ];
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
