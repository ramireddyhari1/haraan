<?php

namespace App\Filament\Resources\PartnerSubscriptions\Pages;

use App\Filament\Resources\PartnerSubscriptions\PartnerSubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPartnerSubscription extends EditRecord
{
    protected static string $resource = PartnerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
