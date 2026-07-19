<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePartner extends CreateRecord
{
    protected static string $resource = PartnerResource::class;

    /**
     * Force the PARTNER role. Use the password the admin typed, falling back to a random
     * one if the field was left blank. The `password` cast hashes it on save.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = 'PARTNER';

        if (empty($data['password'])) {
            $data['password'] = Str::random(16);
        }

        return $data;
    }
}
