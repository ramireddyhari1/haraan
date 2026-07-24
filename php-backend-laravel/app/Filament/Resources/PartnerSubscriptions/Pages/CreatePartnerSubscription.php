<?php

namespace App\Filament\Resources\PartnerSubscriptions\Pages;

use App\Filament\Resources\PartnerSubscriptions\PartnerSubscriptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePartnerSubscription extends CreateRecord
{
    protected static string $resource = PartnerSubscriptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Hand-assigned, not paid through a gateway — phase 2b sets 'razorpay'.
        $data['source'] = 'admin';

        return $data;
    }
}
