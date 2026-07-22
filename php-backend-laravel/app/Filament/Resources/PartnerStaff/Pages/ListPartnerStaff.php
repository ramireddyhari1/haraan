<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerStaff\Pages;

use App\Filament\Resources\PartnerStaff\PartnerStaffResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPartnerStaff extends ListRecords
{
    protected static string $resource = PartnerStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The model is User, whose admin-only policy denies `create` to a
            // partner — which hid Filament's default create button even though the
            // resource's own canCreate() allows it. Authorise against the resource
            // gate (owner-only, partner panel) so owners get a visible button.
            CreateAction::make()
                ->label('Create staff')
                ->icon(Heroicon::OutlinedPlus)
                ->authorize(fn (): bool => PartnerStaffResource::canCreate()),
        ];
    }
}
