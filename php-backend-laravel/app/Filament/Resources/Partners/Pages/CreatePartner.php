<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreatePartner extends CreateRecord
{
    protected static string $resource = PartnerResource::class;

    /** Force the PARTNER role and give the account a random initial password. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = 'PARTNER';
        $data['password'] = Hash::make(Str::random(16));

        return $data;
    }
}
