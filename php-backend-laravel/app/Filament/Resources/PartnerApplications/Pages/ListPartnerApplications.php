<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerApplications\Pages;

use App\Filament\Resources\PartnerApplications\PartnerApplicationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListPartnerApplications extends ListRecords
{
    protected static string $resource = PartnerApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openForm')
                ->label('Open the form')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')
                ->url(route('site.partner.apply'), shouldOpenInNewTab: true),
        ];
    }
}
