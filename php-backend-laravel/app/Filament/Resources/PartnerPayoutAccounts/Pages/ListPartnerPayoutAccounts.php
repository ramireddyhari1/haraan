<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerPayoutAccounts\Pages;

use App\Filament\Resources\PartnerPayoutAccounts\PartnerPayoutAccountResource;
use Filament\Resources\Pages\ListRecords;

class ListPartnerPayoutAccounts extends ListRecords
{
    protected static string $resource = PartnerPayoutAccountResource::class;
}
